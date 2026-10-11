<?php

namespace Database\Factories;

use App\Models\Models\Logement;
use App\Models\Models\Agence;
use Illuminate\Database\Eloquent\Factories\Factory;

class LogementFactory extends Factory
{
    protected $model = Logement::class;

    public function definition(): array
    {
        $types = ['hotel', 'maison', 'studio'];
        $units = ['heure', 'jour', 'mois'];

        return [
            'id_agence' => Agence::inRandomOrder()->value('id_agence') ?? Agence::factory(),
            'titre' => ucfirst($this->faker->words(3, true)),
            'description' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement($types),
            'capacite' => $this->faker->numberBetween(1, 6),
            'adresse' => $this->faker->address(),
            'photo_url' => $this->generateReliableImageUrl(),
            'statut' => $this->faker->randomElement(['actif', 'inactif']),
            'tarif_amount' => $this->faker->randomFloat(2, 80, 1500),
            'tarif_unit' => $this->faker->randomElement($units),
            'devise' => 'MAD',
        ];
    }

    private function generateReliableImageUrl(): string
    {
        // Array of reliable real estate/accommodation images from Unsplash
        $imageIds = [
            'photo-1560448204-e02f11c3d0e2', // Modern hotel room
            'photo-1571003123894-1f0594d2b5d9', // Luxury hotel exterior
            'photo-1564013799919-ab600027ffc6', // Beautiful house exterior
            'photo-1502672260266-1c1ef2d93688', // Cozy apartment interior
            'photo-1522708323590-d24dbb6b0267', // Modern studio apartment
            'photo-1449824913935-59a10b8d2000', // Cabin/house in nature
            'photo-1582063289852-62e3ba2747f8', // Hotel lobby
            'photo-1561501900-3701fa6a0864', // Modern house
            'photo-1484154218962-a197022b5858', // Kitchen interior
            'photo-1586023492125-27b2c045efd7', // Bedroom
            'photo-1545324418-cc1a3fa10c00', // Living room
            'photo-1574362848149-11496d93a7c7', // Hotel exterior
            'photo-1566073771259-6a8506099945', // Modern hotel
            'photo-1568605114967-8130f3a36994', // House with garden
            'photo-1551632811-561732d1e306', // Apartment balcony
        ];

        $randomId = $this->faker->randomElement($imageIds);

        // Return Unsplash URL with proper dimensions
        return "https://images.unsplash.com/{$randomId}?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=60";
    }
}
