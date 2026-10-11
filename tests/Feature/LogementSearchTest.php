<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Assistant\BuildsBookings;
use Tests\TestCase;

class LogementSearchTest extends TestCase
{
    use BuildsBookings;
    use RefreshDatabase;

    public function test_public_search_by_text_and_type(): void
    {
        [, $atlas] = $this->makeAgency('Atlas Voyages');
        $this->makeLogement($atlas, ['titre' => 'Riad Jardin de la Médina', 'type' => 'maison', 'adresse' => 'Médina, Marrakech']);
        $this->makeLogement($atlas, ['titre' => 'Studio Guéliz', 'type' => 'studio', 'adresse' => 'Guéliz, Marrakech']);
        [, $fes] = $this->makeAgency('Fès Médina Tours');
        $this->makeLogement($fes, ['titre' => 'Hôtel Palais Batha', 'type' => 'hotel', 'adresse' => 'Place Batha, Fès']);

        $this->getJson('/api/logements?search=Marrakech')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/logements?search=Fès')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/logements?search=Marrakech&type=studio')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.titre', 'Studio Guéliz');
    }

    public function test_an_agency_lists_and_searches_only_its_own_accommodations(): void
    {
        [$atlasAccount, $atlas] = $this->makeAgency('Atlas Voyages');
        $this->makeLogement($atlas, ['titre' => 'Hôtel Atlas Marrakech']);
        $this->makeLogement($atlas, ['titre' => 'Riad Atlas Médina']);
        [, $fes] = $this->makeAgency('Fès Médina Tours');
        $this->makeLogement($fes, ['titre' => 'Riad Fès el Bali']);

        Sanctum::actingAs($atlasAccount);
        $this->getJson('/api/agency/logements')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/agency/logements?search=Riad')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.titre', 'Riad Atlas Médina');

        Sanctum::actingAs($this->makeClient());
        $this->getJson('/api/agency/logements')->assertForbidden();
    }
}
