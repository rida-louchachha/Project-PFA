<?php

namespace App\Services\Ai\Communication;

use App\Models\Models\BookingNotification;
use App\Models\Models\Reservation;
use App\Models\Models\ReservationConfirmation;
use Illuminate\Support\Carbon;

/**
 * Decides, from the CURRENT database state, whether an automated message may be sent.
 * Returns null when eligible, or a short reason when it must not be sent.
 */
class CommunicationEligibility
{
    /**
     * @param  array{intention_request_hours_before: int, followup_after_hours: int, arrival_reminder_hours_before: int, min_hours_between_messages: int}  $timings
     */
    public function __construct(private readonly array $timings)
    {
    }

    public function check(Reservation $reservation, string $type, ?Carbon $now = null): ?string
    {
        $now ??= Carbon::now();
        $reservation->loadMissing(['paiement', 'confirmation', 'bookingNotifications']);

        if (!in_array($type, BookingNotification::TYPES, true)) {
            return 'unknown_type';
        }

        // Never claim a booking is confirmed/paid unless the database says so
        if ($reservation->statut !== 'confirme') {
            return 'reservation_not_confirmed';
        }
        if ($reservation->paiement?->statut !== 'regle') {
            return 'payment_not_settled';
        }

        $checkIn = Carbon::parse($reservation->date_debut);
        if ($type === BookingNotification::TYPE_BOOKING_CONFIRMATION) {
            return Carbon::parse($reservation->date_fin)->lt($now) ? 'stay_completed' : null;
        }

        // All reminders are for upcoming stays only
        if ($checkIn->lte($now)) {
            return 'stay_started_or_completed';
        }

        $hoursUntilCheckIn = $now->diffInHours($checkIn, false);
        $confirmation = $reservation->confirmation;

        if ($this->recentlyContacted($reservation, $now)) {
            return 'recently_contacted';
        }

        return match ($type) {
            BookingNotification::TYPE_INTENTION_REQUEST => match (true) {
                $hoursUntilCheckIn > $this->timings['intention_request_hours_before'] => 'too_early',
                $confirmation !== null && $confirmation->isAnswered() => 'already_answered',
                default => null,
            },
            BookingNotification::TYPE_INTENTION_FOLLOWUP => match (true) {
                $confirmation === null || $confirmation->requested_at === null => 'intention_not_requested',
                $confirmation->isAnswered() => 'already_answered',
                $confirmation->requested_at->gt($now->copy()->subHours($this->timings['followup_after_hours'])) => 'too_early',
                default => null,
            },
            BookingNotification::TYPE_ARRIVAL_REMINDER => match (true) {
                $hoursUntilCheckIn > $this->timings['arrival_reminder_hours_before'] => 'too_early',
                $confirmation?->intention === ReservationConfirmation::DECLINED => 'customer_declined',
                default => null,
            },
            default => 'unknown_type',
        };
    }

    /** Anti-spam: no two automated messages for the same stay within N hours. */
    private function recentlyContacted(Reservation $reservation, Carbon $now): bool
    {
        $threshold = $now->copy()->subHours($this->timings['min_hours_between_messages']);

        return $reservation->bookingNotifications
            ->where('statut', BookingNotification::STATUT_SENT)
            ->contains(fn (BookingNotification $n) => $n->sent_at !== null && $n->sent_at->gt($threshold));
    }
}
