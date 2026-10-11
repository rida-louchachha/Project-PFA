<?php

namespace Database\Factories;

use App\Models\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UtilisateurFactory extends Factory
{
    protected $model = Utilisateur::class;

    public function definition(): array
    {
        $role = $this->faker->randomElement(['client', 'agence']);
        return [
            'nom'   => $this->faker->lastName(),
            'prenom' => $this->faker->firstName(),
            'email' => $this->faker->unique()->safeEmail(),
            'mot_de_passe' => Hash::make('password'),
            'role'  => $role,
        ];
    }
}
