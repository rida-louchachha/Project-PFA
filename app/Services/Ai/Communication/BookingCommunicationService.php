<?php

namespace App\Services\Ai\Communication;

use App\Models\Models\AiMessage;
use App\Models\Models\BookingNotification;
use App\Models\Models\Reservation;
use App\Notifications\BookingAssistantNotification;
use App\Services\Ai\AssistantService;
use App\Services\Ai\Intent\ArrivalIntentionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Sends one automated booking communication (email + in-app conversation message).
 *
 * Idempotent: the booking_notifications unique key (reservation, type, channel) means a
 * given message is delivered at most once, even if the job is retried or dispatched twice.
 */
class BookingCommunicationService
{
    public function __construct(
        private readonly CommunicationEligibility $eligibility,
        private readonly MessageComposer $composer,
        private readonly AssistantService $assistant,
        private readonly ArrivalIntentionService $intentions,
    ) {
    }

    /**
     * @return string sent | skipped:<reason> | duplicate
     */
    public function send(int $reservationId, string $type): string
    {
        $reservation = Reservation::with(['utilisateur', 'logement.agence', 'paiement', 'confirmation', 'bookingNotifications'])
            ->find($reservationId);

        if (!$reservation || !$reservation->utilisateur) {
            return 'skipped:reservation_not_found';
        }

        // Re-check against the real database state at send time (not at dispatch time)
        if (($reason = $this->eligibility->check($reservation, $type)) !== null) {
            Log::info('TravelPlan AI communication skipped', ['reservation' => $reservationId, 'type' => $type, 'reason' => $reason]);

            return "skipped:{$reason}";
        }

        try {
            $log = BookingNotification::firstOrCreate(
                ['id_reservation' => $reservationId, 'type' => $type, 'channel' => 'mail'],
                ['statut' => BookingNotification::STATUT_PROCESSING],
            );
        } catch (QueryException) {
            // Lost a race on the unique key: another worker owns this message
            return 'duplicate';
        }

        if ($log->statut === BookingNotification::STATUT_SENT) {
            return 'duplicate';
        }

        $log->increment('attempts');

        try {
            $message = $this->composer->compose($reservation, $type);

            $reservation->utilisateur->notify((new BookingAssistantNotification($message, $type))->locale('fr'));

            $conversation = $this->assistant->openConversation($reservation->utilisateur, $reservation, withWelcome: false);

            // Idempotent in-app copy (a retry after a crash must not duplicate it)
            if (!$conversation->messages()->where('kind', $type)->exists()) {
                $this->assistant->addAssistantMessage($conversation, $message->chatText(), $type, [
                    'provider' => $message->source === 'ai' ? 'ai_personalised' : 'template',
                    'automated' => true,
                ]);
            }

            if (in_array($type, [BookingNotification::TYPE_INTENTION_REQUEST, BookingNotification::TYPE_INTENTION_FOLLOWUP], true)) {
                $this->intentions->markRequested($reservation);
            }

            $log->forceFill([
                'statut' => BookingNotification::STATUT_SENT,
                'content_source' => $message->source,
                'error' => null,
                'sent_at' => now(),
            ])->save();

            return 'sent';
        } catch (\Throwable $e) {
            $log->forceFill([
                'statut' => BookingNotification::STATUT_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ])->save();

            Log::error('TravelPlan AI communication failed', ['reservation' => $reservationId, 'type' => $type, 'error' => $e->getMessage()]);

            throw $e; // let the queue retry
        }
    }
}
