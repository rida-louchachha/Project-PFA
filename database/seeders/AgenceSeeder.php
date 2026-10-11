<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Models\Agence;

class AgenceSeeder extends Seeder
{
    public function run(): void
    {
        Agence::factory()->count(8)->create();
    }
}
