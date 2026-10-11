<?php

namespace Database\Factories;

use App\Models\Models\Reservation;
use App\Models\Models\Utilisateur;
use App\Models\Models\Logement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $start = Carbon::now()->addDays($this->faker->numberBetween(0, 30))->setTime(
            $this->faker->numberBetween(9, 18),
            0
        );
        $durationHours = $this->faker->randomElement([2, 4, 24, 48, 72, 168, 720]); // up to ~1 month
        $end = (clone $start)->addHours($durationHours);

        $logement = Logement::inRandomOrder()->first() ?? Logement::factory()->create();
        $nb = $this->faker->numberBetween(1, max(1, $logement->capacite));

        // simple amount calc (rough): convert hours to unit
        $amount = match ($logement->tarif_unit) {
            'heure' => $logement->tarif_amount * max(1, (int)ceil($durationHours)),
            'jour'  => $logement->tarif_amount * max(1, (int)ceil($durationHours / 24)),
            'mois'  => $logement->tarif_amount * max(1, (int)ceil($durationHours / 720)),
            default => $logement->tarif_amount,
        };

        return [
            'id_utilisateur' => Utilisateur::where('role', 'client')->inRandomOrder()->value('id_utilisateur')
                ?? Utilisateur::factory()->create(['role' => 'client'])->id_utilisateur,
            'id_logement' => $logement->id_logement,
            'date_debut' => $start,
            'date_fin'   => $end,
            'nb_personnes' => $nb,
            'statut' => $this->faker->randomElement(['en_attente', 'confirme', 'annule']),
            'montant_total' => round($amount, 2),
        ];
    }
}
