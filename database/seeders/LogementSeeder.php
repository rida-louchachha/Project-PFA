<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Models\Logement;

class LogementSeeder extends Seeder
{
    public function run(): void
    {
        // Many logements; mix of active/inactive
        Logement::factory()->count(40)->create();
    }
}
