<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Models\Utilisateur;
use Illuminate\Support\Facades\Hash;

class UtilisateurSeeder extends Seeder
{
    public function run(): void
    {
        // Admin/demo accounts (optional)
        Utilisateur::updateOrCreate(
            ['email' => 'client@example.com'],
            [
                'nom' => 'Client',
                'prenom' => 'Demo',
                'mot_de_passe' => Hash::make('password'),
                'role' => 'client'
            ]
        );

        Utilisateur::updateOrCreate(
            ['email' => 'agence@example.com'],
            [
                'nom' => 'Agence',
                'prenom' => 'Demo',
                'mot_de_passe' => Hash::make('password'),
                'role' => 'agence'
            ]
        );

        // Bulk random users
        Utilisateur::factory()->count(15)->create(['role' => 'client']);
        Utilisateur::factory()->count(5)->create(['role' => 'agence']);
    }
}
