<?php

namespace Database\Factories;

use App\Models\Models\Avis;
use App\Models\Models\Utilisateur;
use App\Models\Models\Agence;
use Illuminate\Database\Eloquent\Factories\Factory;

class AvisFactory extends Factory
{
    protected $model = Avis::class;

    public function definition(): array
    {
        return [
            'id_utilisateur' => Utilisateur::where('role', 'client')->inRandomOrder()->value('id_utilisateur')
                ?? Utilisateur::factory()->create(['role' => 'client'])->id_utilisateur,
            'id_agence' => Agence::inRandomOrder()->value('id_agence') ?? Agence::factory(),
            'note' => $this->faker->numberBetween(3, 5),
            'commentaire' => $this->faker->sentence(12),
            'date_avis' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
        ];
    }
}
