<?php

namespace App\Services\Booking;

use App\Models\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for the existing cancellation and refund rules.
 * Used by ReservationController / PaymentController and by TravelPlan AI,
 * so the assistant can never describe rules that differ from what the API enforces.
 */
class BookingPolicy
{
    /** A reservation cannot be cancelled less than 24 hours before check-in. */
    public const MIN_HOURS_BEFORE_CHECKIN_TO_CANCEL = 24;

    /**
     * True when check-in is at least 24 hours away.
     * Signed diff: a past check-in is negative, so it is outside the window too.
     */
    public function isCancellationWindowOpen(CarbonInterface $checkIn, ?CarbonInterface $now = null): bool
    {
        $now ??= Carbon::now();

        return $now->diffInDays($checkIn, false) >= 1;
    }

    /**
     * @return array{allowed: bool, reason: string|null}
     */
    public function cancellationCheck(Reservation $reservation, ?CarbonInterface $now = null): array
    {
        if ($reservation->statut === 'annule') {
            return ['allowed' => false, 'reason' => 'already_cancelled'];
        }

        if (!$this->isCancellationWindowOpen(Carbon::parse($reservation->date_debut), $now)) {
            return ['allowed' => false, 'reason' => 'less_than_24h_before_checkin'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Share of the paid amount refunded if the reservation is cancelled now:
     * more than 7 days => 100%, 3 to 7 days => 50%, less than 3 days => 25%.
     */
    public function refundRate(CarbonInterface $checkIn, ?CarbonInterface $now = null): float
    {
        $now ??= Carbon::now();
        $daysUntilCheckIn = $now->diffInDays($checkIn, false);

        if ($daysUntilCheckIn > 7) {
            return 1.0;
        }

        if ($daysUntilCheckIn >= 3) {
            return 0.5;
        }

        return 0.25;
    }
}
