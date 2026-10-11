<?php

namespace App\Services\Ai\Intent;

use App\Models\Models\AiConversation;
use App\Models\Models\AiMessage;
use App\Models\Models\Reservation;
use App\Models\Models\ReservationConfirmation;
use Illuminate\Support\Carbon;

/**
 * Records the customer's arrival intention in reservation_confirmations.
 *
 * It never changes reservations.statut or paiements: a "declined" answer is
 * recorded and surfaced to the agency, the booking itself is left untouched.
 */
class ArrivalIntentionService
{
    public function __construct(private readonly float $minConfidence = 0.6)
    {
    }

    /** Only upcoming, non-cancelled stays collect an arrival intention. */
    public function isEligible(?Reservation $reservation): bool
    {
        return $reservation !== null
            && $reservation->statut !== 'annule'
            && Carbon::parse($reservation->date_debut)->isFuture();
    }

    /** True when TravelPlan AI has asked the question and the answer is still open. */
    public function isAwaitingAnswer(Reservation $reservation): bool
    {
        $confirmation = $reservation->confirmation;

        return $confirmation !== null
            && $confirmation->requested_at !== null
            && in_array($confirmation->intention, [ReservationConfirmation::PENDING, ReservationConfirmation::UNCERTAIN], true);
    }

    /**
     * @return string|null The intention recorded, or null when nothing was recorded.
     */
    public function record(Reservation $reservation, AiConversation $conversation, AiMessage $message, IntentionResult $result): ?string
    {
        if (!$result->valid
            || !in_array($result->intention, ReservationConfirmation::RECORDABLE, true)
            || $result->confidence < $this->minConfidence
            || !$this->isEligible($reservation)) {
            return null;
        }

        $current = $reservation->confirmation?->intention;
        $keepsDecision = $result->intention === ReservationConfirmation::HUMAN_ASSISTANCE_REQUIRED
            && in_array($current, [
                ReservationConfirmation::CONFIRMED,
                ReservationConfirmation::DECLINED,
                ReservationConfirmation::MODIFICATION_REQUESTED,
            ], true);

        // Asking for a human does not erase an arrival decision already given
        if (!$keepsDecision) {
            ReservationConfirmation::updateOrCreate(
                ['id_reservation' => $reservation->id_reservation],
                [
                    'intention' => $result->intention,
                    'confidence' => round($result->confidence, 2),
                    'source' => 'ai_classifier',
                    'id_message' => $message->id_message,
                    'responded_at' => now(),
                ],
            );
        }

        $message->forceFill(['intent' => $result->intention])->save();

        // Requests the AI cannot fulfil are handed to the agency
        if ($result->intention === ReservationConfirmation::HUMAN_ASSISTANCE_REQUIRED) {
            $conversation->requestHandoff('Client : demande à parler à un conseiller');
        } elseif ($result->intention === ReservationConfirmation::MODIFICATION_REQUESTED) {
            $conversation->requestHandoff('Client : demande de modification de la réservation');
        }

        $reservation->unsetRelation('confirmation');

        return $result->intention;
    }

    /** Marks that the arrival question was asked (keeps any answer already given). */
    public function markRequested(Reservation $reservation): ReservationConfirmation
    {
        $confirmation = ReservationConfirmation::firstOrCreate(
            ['id_reservation' => $reservation->id_reservation],
            ['intention' => ReservationConfirmation::PENDING, 'source' => 'system'],
        );

        if ($confirmation->requested_at === null) {
            $confirmation->forceFill(['requested_at' => now()])->save();
        }

        return $confirmation;
    }
}
