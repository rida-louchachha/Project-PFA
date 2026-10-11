<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Models\Avis;

class AvisSeeder extends Seeder
{
    public function run(): void
    {
        Avis::factory()->count(30)->create();
    }
}
