<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Assistant\BuildsBookings;
use Tests\TestCase;

/**
 * Regression test for the Carbon 3 signed-diff bug in ReservationController::cancel
 * (every future reservation used to be rejected as "less than 24 hours before check-in").
 */
class CancellationPolicyTest extends TestCase
{
    use BuildsBookings;
    use RefreshDatabase;

    public function test_a_reservation_can_be_cancelled_more_than_24_hours_before_check_in(): void
    {
        $stay = $this->bookedStay(['date_debut' => Carbon::now()->addDays(3), 'date_fin' => Carbon::now()->addDays(5)]);
        Sanctum::actingAs($stay['client']);

        $this->putJson("/api/reservations/{$stay['reservation']->id_reservation}/cancel")->assertOk();
        $this->assertDatabaseHas('reservations', ['id_reservation' => $stay['reservation']->id_reservation, 'statut' => 'annule']);
    }

    public function test_cancellation_is_refused_less_than_24_hours_before_check_in(): void
    {
        $stay = $this->bookedStay(['date_debut' => Carbon::now()->addHours(10), 'date_fin' => Carbon::now()->addDays(2)]);
        Sanctum::actingAs($stay['client']);

        $this->putJson("/api/reservations/{$stay['reservation']->id_reservation}/cancel")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Cannot cancel reservation less than 24 hours before check-in');
    }
}
