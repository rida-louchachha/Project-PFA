<?php

namespace Database\Factories;

use App\Models\Models\Paiement;
use App\Models\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaiementFactory extends Factory
{
    protected $model = Paiement::class;

    public function definition(): array
    {
        $reservation = Reservation::inRandomOrder()->first() ?? Reservation::factory()->create();
        $status = $this->faker->randomElement(['en_attente', 'regle', 'echoue']);

        return [
            'id_reservation' => $reservation->id_reservation,
            'mode' => $this->faker->randomElement(['especes', 'terminal', 'en_ligne']),
            'montant' => $reservation->montant_total,
            'date_paiement' => $reservation->date_debut->copy()->subDays(1),
            'statut' => $status,
            'reference' => strtoupper($this->faker->bothify('PAY-####-????')),
        ];
    }
}
