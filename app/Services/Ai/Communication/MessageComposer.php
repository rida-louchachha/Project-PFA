<?php

namespace App\Services\Ai\Communication;

use App\Models\Models\BookingNotification;
use App\Models\Models\Reservation;
use App\Services\Ai\Contracts\LanguageModel;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Prompts;
use App\Services\Ai\Support\GroundingGuard;
use App\Services\Ai\Support\ReservationFacts;
use Illuminate\Support\Facades\Log;

/**
 * Builds automated booking messages.
 *
 * Facts (dates, amounts, statuses, addresses) are always rendered by templates from
 * MySQL. The LLM only contributes one personalised sentence, which is rejected if it
 * contains any digit, link or is too long — then the template sentence is used.
 */
class MessageComposer
{
    public function __construct(
        private readonly LanguageModel $model,
        private readonly string $frontendUrl,
    ) {
    }

    public function compose(Reservation $reservation, string $type): ComposedMessage
    {
        $reservation->loadMissing(['utilisateur', 'logement.agence', 'paiement']);
        $facts = ReservationFacts::details($reservation);
        $firstName = $reservation->utilisateur?->prenom ?? '';
        $title = $facts['logement']['titre'] ?? 'votre logement';
        $agency = $facts['agence']['nom'] ?? "l'agence";
        $from = $facts['date_arrivee_label'];
        $to = $facts['date_depart_label'];

        [$subject, $body] = match ($type) {
            BookingNotification::TYPE_BOOKING_CONFIRMATION => [
                "Votre réservation n°{$facts['reservation_id']} est confirmée",
                "Votre réservation au « {$title} » du {$from} au {$to} est confirmée et votre paiement de {$facts['paiement']['montant']} {$facts['devise']} est enregistré. "
                . "Je suis l'assistant intelligent TravelPlan : je peux répondre à vos questions sur votre séjour.",
            ],
            BookingNotification::TYPE_INTENTION_REQUEST => [
                'Votre séjour approche : confirmez-vous votre venue ?',
                "Votre séjour au « {$title} » commence le {$from}. Confirmez-vous que vous prévoyez toujours de venir ? "
                . 'Répondez simplement dans la conversation TravelPlan AI (par exemple « Oui, je confirme »).',
            ],
            BookingNotification::TYPE_INTENTION_FOLLOWUP => [
                'Petit rappel : confirmez-vous votre venue ?',
                "Nous n'avons pas encore reçu votre réponse pour votre séjour au « {$title} » à partir du {$from}. "
                . "Pouvez-vous nous confirmer que vous venez toujours ? Si vos plans ont changé, dites-le-moi et je vous indiquerai la marche à suivre.",
            ],
            BookingNotification::TYPE_ARRIVAL_REMINDER => [
                'Votre arrivée approche',
                "Petit rappel : votre séjour au « {$title} » commence le {$from}."
                . (!empty($facts['logement']['adresse']) ? " Adresse : {$facts['logement']['adresse']}." : '')
                . " Votre agence : {$agency}. Je reste disponible dans TravelPlan pour toute question.",
            ],
        };

        $personal = $this->personalLine($type, $facts);

        return new ComposedMessage(
            subject: "TravelPlan — {$subject}",
            greeting: $firstName !== '' ? "Bonjour {$firstName} !" : 'Bonjour !',
            personalLine: $personal,
            body: $body,
            facts: array_filter([
                'Réservation' => "n°{$facts['reservation_id']}",
                'Logement' => $title,
                'Adresse' => $facts['logement']['adresse'] ?? null,
                'Arrivée' => $from,
                'Départ' => $to,
                'Voyageurs' => (string) $facts['nb_personnes'],
                'Montant total' => "{$facts['montant_total']} {$facts['devise']}",
                'Paiement' => $facts['paiement']['statut_label'] ?? 'aucun paiement enregistré',
                'Agence' => $agency,
            ]),
            actionLabel: "Ouvrir l'assistant TravelPlan",
            actionUrl: "{$this->frontendUrl}/assistant/{$facts['reservation_id']}",
            source: $personal !== null ? 'ai' : 'template',
        );
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function personalLine(string $type, array $facts): ?string
    {
        // Minimal, non-identifying context: no name, email, amount or date is sent
        $instruction = "Type de message : {$type}. Logement : " . ($facts['logement']['type'] ?? 'logement')
            . ' « ' . ($facts['logement']['titre'] ?? '') . ' »'
            . (!empty($facts['logement']['adresse']) ? ', ' . $facts['logement']['adresse'] : '')
            . '. Agence : ' . ($facts['agence']['nom'] ?? '') . '.';

        try {
            $line = $this->model->write(Prompts::OUTBOUND_WRITER, $instruction);
        } catch (\Throwable $e) {
            Log::info('TravelPlan AI personalisation unavailable, using template', [
                'reason' => $e instanceof AiProviderException ? $e->reason : 'unexpected_error',
            ]);

            return null;
        }

        if ($line === null) {
            return null;
        }

        $line = trim(preg_replace('/\s+/', ' ', strip_tags($line)) ?? '');

        // Hard guard: the AI sentence must not carry facts (digits), links, or be long
        if ($line === ''
            || mb_strlen($line) > 220
            || GroundingGuard::containsDigits($line)
            || preg_match('#https?://|www\.#i', $line) === 1) {
            Log::info('TravelPlan AI personalised line rejected by the grounding guard');

            return null;
        }

        return $line;
    }
}
