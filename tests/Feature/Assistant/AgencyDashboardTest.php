<?php

namespace Tests\Feature\Assistant;

use App\Models\Models\ReservationConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgencyDashboardTest extends TestCase
{
    use BuildsBookings;
    use RefreshDatabase;

    public function test_dashboard_reflects_customer_answers_and_supports_human_handoff(): void
    {
        $client = $this->makeClient();
        $otherClient = $this->makeClient('Salma');
        [$agencyAccount, $agency] = $this->makeAgency();
        $logement = $this->makeLogement($agency);

        $confirmedStay = $this->makeReservation($client, $logement);
        $this->pay($confirmedStay);
        $silentStay = $this->makeReservation($otherClient, $logement, ['date_debut' => now()->addDays(20), 'date_fin' => now()->addDays(22)]);
        $this->pay($silentStay);

        // The customer confirms through the assistant, then asks for a human
        ReservationConfirmation::create(['id_reservation' => $confirmedStay->id_reservation, 'intention' => 'pending', 'requested_at' => now()]);
        Sanctum::actingAs($client);
        $conversationId = $this->postJson('/api/assistant/conversations', ['id_reservation' => $confirmedStay->id_reservation])->json('data.id');
        $this->postJson("/api/assistant/conversations/{$conversationId}/messages", ['content' => 'Oui, je confirme.'])->assertOk();
        $this->postJson("/api/assistant/conversations/{$conversationId}/messages", ['content' => "Je veux parler à l'agence, svp"])->assertOk();

        Sanctum::actingAs($agencyAccount);
        $this->getJson('/api/agency/assistant/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.upcoming_total', 2)
            ->assertJsonPath('data.stats.awaiting_response', 1)
            ->assertJsonPath('data.stats.handoffs_open', 1)
            ->assertJsonPath('data.handoffs.0.customer.prenom', 'Youssef');

        // Full history with audit data is visible to the owning agency
        $this->getJson("/api/agency/assistant/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('data.messages.1.intent', 'confirmed');

        // Human reply, visible to the customer, then the handoff is resolved
        $this->postJson("/api/agency/assistant/conversations/{$conversationId}/messages", ['content' => 'Bonjour, je suis Samir de l\'agence. Comment puis-je vous aider ?'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'agent');
        $this->postJson("/api/agency/assistant/conversations/{$conversationId}/resolve")
            ->assertOk()
            ->assertJsonPath('data.statut', 'active');

        Sanctum::actingAs($client);
        $messages = $this->getJson("/api/assistant/conversations/{$conversationId}")->assertOk()->json('data.messages');
        $agentMessage = collect($messages)->firstWhere('role', 'agent');
        $this->assertSame('Conseiller · Atlas Voyages', $agentMessage['author']);
        $this->assertArrayNotHasKey('audit', $agentMessage);
    }
}
