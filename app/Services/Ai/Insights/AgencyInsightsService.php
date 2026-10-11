<?php

namespace App\Services\Ai\Insights;

use App\Models\Models\Agence;
use App\Models\Models\Logement;
use App\Models\Models\Reservation;
use App\Models\Models\ReservationConfirmation;
use App\Services\Ai\Contracts\LanguageModel;
use App\Services\Ai\Support\GroundingGuard;
use App\Support\Currency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Agency KPIs (computed from MySQL) + a short AI-written summary for the manager.
 * The summary is accepted only if every number it contains comes from the KPIs.
 */
class AgencyInsightsService
{
    private const WRITER = <<<'TXT'
Tu es l'analyste de TravelPlan AI. Rédige pour le gérant d'une agence de voyage marocaine un résumé en français de 2 à 3 phrases à partir des indicateurs JSON fournis, puis une recommandation d'action concrète commençant par « Action conseillée : ».
Utilise uniquement les chiffres présents dans les indicateurs, sans les recalculer ni en inventer. Pas de Markdown, pas de liste.
TXT;

    public function __construct(private readonly LanguageModel $model)
    {
    }

    /** @return array<string, mixed> */
    public function forAgency(Agence $agency): array
    {
        $today = Carbon::today();
        $windowEnd = $today->copy()->addDays(30);
        $listingIds = Logement::where('id_agence', $agency->id_agence)->pluck('id_logement');
        $activeListings = Logement::where('id_agence', $agency->id_agence)->where('statut', 'actif')->count();

        $active = Reservation::with(['confirmation', 'logement', 'paiement'])
            ->whereIn('id_logement', $listingIds)
            ->where('statut', '!=', 'annule')
            ->get();

        $next30 = $active->filter(fn (Reservation $r) => $r->date_debut->gte($today) && $r->date_debut->lt($windowEnd));
        $confirmedNext30 = $next30->where('statut', 'confirme');

        // Occupancy: booked nights inside the next 30 days / available nights
        $bookedNights = $active->sum(function (Reservation $r) use ($today, $windowEnd) {
            $start = $r->date_debut->copy()->startOfDay()->max($today);
            $end = $r->date_fin->copy()->startOfDay()->min($windowEnd);

            return max(0, (int) $start->diffInDays($end));
        });
        $occupancy = $activeListings > 0 ? (int) round($bookedNights / ($activeListings * 30) * 100) : 0;

        $requested = $active->filter(fn (Reservation $r) => $r->date_debut->gte($today) && $r->confirmation?->requested_at !== null);
        $confirmedIntentions = $requested->filter(fn (Reservation $r) => $r->confirmation->intention === ReservationConfirmation::CONFIRMED)->count();

        $arrivals = [];
        for ($d = 0; $d < 14; $d++) {
            $day = $today->copy()->addDays($d);
            $arrivals[] = [
                'date' => $day->toDateString(),
                'label' => $day->locale('fr')->translatedFormat('D j'),
                'count' => $active->filter(fn (Reservation $r) => $r->date_debut->isSameDay($day))->count(),
            ];
        }

        $top = $active->countBy('id_logement')->sortDesc();
        $topListing = $top->isNotEmpty() ? Logement::find($top->keys()->first())?->titre : null;

        $kpis = [
            'revenus_prevus_30j' => (int) round($confirmedNext30->sum(fn (Reservation $r) => (float) $r->montant_total)),
            'sejours_30j' => $next30->count(),
            'taux_occupation_30j' => $occupancy,
            'demandes_confirmation' => $requested->count(),
            'venues_confirmees' => $confirmedIntentions,
            'taux_confirmation' => $requested->count() > 0 ? (int) round($confirmedIntentions / $requested->count() * 100) : null,
            'en_attente_paiement' => $active->filter(fn (Reservation $r) => $r->statut === 'en_attente' && $r->date_debut->gte($today))->count(),
            'note_moyenne' => round((float) $agency->score_classement, 1),
            'nombre_avis' => $agency->avis()->count(),
            'logements_actifs' => $activeListings,
            'logement_le_plus_reserve' => $topListing,
        ];

        [$summary, $source] = $this->summary($agency, $kpis);

        return [
            'devise' => Currency::label(),
            'kpis' => $kpis,
            'arrivees_14j' => $arrivals,
            'resume' => $summary,
            'resume_source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $kpis
     * @return array{0: string, 1: string}
     */
    private function summary(Agence $agency, array $kpis): array
    {
        $template = $this->template($kpis);

        try {
            $text = $this->model->write(self::WRITER, 'Agence : ' . $agency->nom . "\nIndicateurs : " . json_encode($kpis, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::info('TravelPlan AI insights summary unavailable, using template');

            return [$template, 'template'];
        }

        if ($text === null || trim($text) === '' || mb_strlen($text) > 700) {
            return [$template, 'template'];
        }

        // Grounding: every number must come from the KPIs
        if (GroundingGuard::ungroundedNumbers($text, [$kpis]) !== []) {
            Log::info('TravelPlan AI insights summary rejected (ungrounded numbers)');

            return [$template, 'template'];
        }

        return [trim($text), 'ai'];
    }

    /** @param array<string, mixed> $k */
    private function template(array $k): string
    {
        $currency = Currency::label();
        $revenue = number_format($k['revenus_prevus_30j'], 0, ',', ' ');
        $sentences = [
            "Sur les 30 prochains jours, vous avez {$k['sejours_30j']} séjour(s) prévu(s) pour {$revenue} {$currency} de revenus confirmés, avec un taux d'occupation de {$k['taux_occupation_30j']} %.",
        ];

        if ($k['taux_confirmation'] !== null) {
            $sentences[] = "TravelPlan AI a obtenu {$k['venues_confirmees']} confirmation(s) de venue sur {$k['demandes_confirmation']} demande(s), soit {$k['taux_confirmation']} %.";
        }

        if ($k['logement_le_plus_reserve']) {
            $sentences[] = "Votre logement le plus réservé est « {$k['logement_le_plus_reserve']} ».";
        }

        $sentences[] = match (true) {
            $k['en_attente_paiement'] > 0 => "Action conseillée : relancez les {$k['en_attente_paiement']} réservation(s) en attente de paiement.",
            $k['taux_occupation_30j'] < 30 => 'Action conseillée : proposez une offre spéciale sur vos logements les moins réservés pour remplir le calendrier.',
            default => 'Action conseillée : contactez les clients qui n\'ont pas encore confirmé leur venue.',
        };

        return implode(' ', $sentences);
    }
}
