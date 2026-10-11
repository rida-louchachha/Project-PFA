<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Models\Reservation;
use App\Models\Models\Paiement;

class PaiementSeeder extends Seeder
{
    public function run(): void
    {
        // ~60% of reservations have a payment
        Reservation::inRandomOrder()->take((int)(Reservation::count() * 0.6))->get()
            ->each(function ($r) {
                // Unique per reservation enforced by DB
                Paiement::factory()->create(['id_reservation' => $r->id_reservation]);
            });
    }
}
