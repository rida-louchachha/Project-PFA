<?php

namespace Database\Factories;

use App\Models\Models\Agence;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgenceFactory extends Factory
{
    protected $model = Agence::class;

    public function definition(): array
    {
        return [
            'nom' => $this->faker->company() . ' Immobilier',
            'adresse' => $this->faker->address(),
            'telephone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'description' => $this->faker->sentence(12),
            'score_classement' => $this->faker->randomFloat(2, 3.5, 5.0),
        ];
    }
}
