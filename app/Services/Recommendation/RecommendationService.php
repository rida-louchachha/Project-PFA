<?php

namespace App\Services\Recommendation;

use App\Models\Models\Logement;
use App\Models\Models\Reservation;
use App\Models\User;
use App\Services\Ai\Search\SmartSearchService;
use App\Support\Currency;
use Illuminate\Support\Collection;

/**
 * "Recommandé pour vous": explainable content-based recommendations.
 *
 * Profile = the customer's past and upcoming bookings (preferred types, cities, budget,
 * group size). Each active listing gets a score from transparent criteria, and every
 * recommendation carries the human-readable reasons behind its score.
 */
class RecommendationService
{
    private const TYPE_LABELS = ['hotel' => 'les hôtels', 'maison' => 'les riads et maisons', 'studio' => 'les studios'];

    /**
     * @return array{personalised: bool, profile: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function forUser(User $user, int $limit = 6): array
    {
        $history = Reservation::with('logement.agence')
            ->where('id_utilisateur', $user->id_utilisateur)
            ->where('statut', '!=', 'annule')
            ->get()
            ->filter(fn (Reservation $r) => $r->logement !== null);

        $alreadyBooked = $history->pluck('id_logement')->all();
        $candidates = Logement::with('agence')
            ->where('statut', 'actif')
            ->whereNotIn('id_logement', $alreadyBooked)
            ->get();

        $profile = $this->profile($history);

        $scored = $candidates->map(function (Logement $l) use ($profile) {
            [$score, $reasons] = $this->score($l, $profile);

            return ['logement' => $l, 'score' => $score, 'reasons' => $reasons];
        });

        // Highest score first, at most 2 listings per city for variety
        $perCity = [];
        $items = $scored->sortByDesc(fn ($s) => [$s['score'], (float) $s['logement']->agence?->score_classement])
            ->filter(function ($s) use (&$perCity) {
                $city = SmartSearchService::cityOf($s['logement']->adresse) ?? '?';
                $perCity[$city] = ($perCity[$city] ?? 0) + 1;

                return $perCity[$city] <= 2;
            })
            ->take($limit)
            ->map(fn ($s) => [
                'id' => $s['logement']->id_logement,
                'titre' => $s['logement']->titre,
                'type' => $s['logement']->type,
                'ville' => SmartSearchService::cityOf($s['logement']->adresse),
                'photo_url' => $s['logement']->photo_url,
                'tarif_amount' => $s['logement']->tarif_amount,
                'tarif_unit' => $s['logement']->tarif_unit,
                'devise' => Currency::label($s['logement']->devise),
                'capacite' => $s['logement']->capacite,
                'agence' => $s['logement']->agence?->nom,
                'note' => $s['logement']->agence?->score_classement,
                'score' => $s['score'],
                'raisons' => array_slice($s['reasons'], 0, 3),
            ])
            ->values()
            ->all();

        return [
            'personalised' => $history->isNotEmpty(),
            'profile' => [
                'types' => array_keys($profile['types']),
                'villes' => array_keys($profile['cities']),
                'budget_moyen' => $profile['avg_price'] ? round($profile['avg_price']) : null,
            ],
            'items' => $items,
        ];
    }

    /**
     * @param  Collection<int, Reservation>  $history
     * @return array{types: array<string, int>, cities: array<string, int>, avg_price: ?float, group: ?float}
     */
    private function profile(Collection $history): array
    {
        $types = $history->countBy(fn (Reservation $r) => $r->logement->type)->sortDesc()->all();
        $cities = $history->countBy(fn (Reservation $r) => SmartSearchService::cityOf($r->logement->adresse))->sortDesc()->all();

        return [
            'types' => $types,
            'cities' => $cities,
            'avg_price' => $history->isNotEmpty() ? (float) $history->avg(fn (Reservation $r) => (float) $r->logement->tarif_amount) : null,
            'group' => $history->isNotEmpty() ? (float) $history->avg('nb_personnes') : null,
        ];
    }

    /**
     * @param  array{types: array<string, int>, cities: array<string, int>, avg_price: ?float, group: ?float}  $profile
     * @return array{0: float, 1: list<string>}
     */
    private function score(Logement $l, array $profile): array
    {
        $score = 0.0;
        $reasons = [];
        $rating = (float) ($l->agence?->score_classement ?? 0);
        $city = SmartSearchService::cityOf($l->adresse);

        if ($profile['types'] !== [] && array_key_first($profile['types']) === $l->type) {
            $score += 3;
            $reasons[] = 'Vous aimez ' . self::TYPE_LABELS[$l->type];
        }

        if ($profile['avg_price']) {
            $ratio = (float) $l->tarif_amount / $profile['avg_price'];
            if ($ratio >= 0.6 && $ratio <= 1.3) {
                $score += 2;
                $reasons[] = 'Dans votre budget habituel';
            } elseif ($ratio < 0.6) {
                $score += 1;
                $reasons[] = 'Petit prix';
            }
        }

        if ($profile['group'] && $l->capacite >= $profile['group']) {
            $score += 1;
        }

        if ($profile['cities'] !== [] && $city && !array_key_exists($city, $profile['cities'])) {
            $score += 1.5;
            $reasons[] = "Nouvelle destination : {$city}";
        } elseif ($city && isset($profile['cities'][$city])) {
            $score += 0.5;
            $reasons[] = "Vous connaissez déjà {$city}";
        }

        if ($rating >= 4.5) {
            $score += 2;
            $reasons[] = 'Agence très bien notée (' . number_format($rating, 1, ',', '') . '/5)';
        } elseif ($rating >= 4.0) {
            $score += 1;
        }

        // New customers: best-rated listings across varied destinations
        if ($profile['types'] === []) {
            $reasons[] = 'Coup de cœur TravelPlan';
        }

        return [round($score, 2), $reasons];
    }
}
