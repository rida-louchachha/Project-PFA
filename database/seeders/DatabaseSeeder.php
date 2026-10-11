<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Coherent Moroccan demo data (agencies, listings, clients, bookings, payments, reviews).
        // The previous random (Faker) seeders are kept in this folder and can still be called
        // individually, e.g. php artisan db:seed --class=LogementSeeder
        $this->call([
            MoroccanDemoSeeder::class,
        ]);
    }
}
