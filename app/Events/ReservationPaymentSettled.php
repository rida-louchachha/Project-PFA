<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a payment is recorded as "regle" and the reservation set to "confirme".
 * Carries only the id: listeners must re-read the real database state.
 */
class ReservationPaymentSettled
{
    use Dispatchable;

    public function __construct(public readonly int $reservationId)
    {
    }
}
