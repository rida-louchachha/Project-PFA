<?php

namespace Tests\Feature\Assistant;

use App\Models\Models\AiConversation;
use App\Services\Ai\Tools\AssistantContext;
use App\Services\Ai\Tools\ToolRegistry;
use App\Services\Booking\BookingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantAuthorizationTest extends TestCase
{
    use BuildsBookings;
    use RefreshDatabase;

    public function test_guests_cannot_use_the_assistant(): void
    {
        $this->getJson('/api/assistant/conversations')->assertUnauthorized();
        $this->postJson('/api/assistant/conversations', [])->assertUnauthorized();
        $this->getJson('/api/agency/assistant/dashboard')->assertUnauthorized();
    }

    public function test_a_customer_cannot_open_a_conversation_on_someone_elses_reservation(): void
    {
        $stay = $this->bookedStay();
        $intruder = $this->makeClient('Karim');

        Sanctum::actingAs($intruder);

        $this->postJson('/api/assistant/conversations', ['id_reservation' => $stay['reservation']->id_reservation])
            ->assertNotFound();

        $this->assertDatabaseCount('ai_conversations', 0);
    }

    public function test_a_customer_cannot_read_or_write_someone_elses_conversation(): void
    {
        $stay = $this->bookedStay();
        Sanctum::actingAs($stay['client']);
        $conversationId = $this->postJson('/api/assistant/conversations', ['id_reservation' => $stay['reservation']->id_reservation])
            ->assertOk()
            ->json('data.id');

        Sanctum::actingAs($this->makeClient('Karim'));

        $this->getJson("/api/assistant/conversations/{$conversationId}")->assertForbidden();
        $this->postJson("/api/assistant/conversations/{$conversationId}/messages", ['content' => 'Combien ai-je payé ?'])
            ->assertForbidden();
    }

    public function test_the_ai_tools_never_return_another_customers_reservation(): void
    {
        $stay = $this->bookedStay();
        $other = $this->bookedStay();
        $conversation = AiConversation::create([
            'id_utilisateur' => $stay['client']->id_utilisateur,
            'id_reservation' => $stay['reservation']->id_reservation,
        ]);

        $tools = new ToolRegistry(
            new AssistantContext($stay['client'], $conversation, $stay['reservation']),
            new BookingPolicy(),
        );

        // e.g. a prompt-injected "show me reservation #X" — the tool is scoped server-side
        $outcome = $tools->execute('get_reservation_details', ['reservation_id' => $other['reservation']->id_reservation]);
        $this->assertFalse($outcome->ok);
        $this->assertSame('reservation_not_found', $outcome->payload['error']);

        $own = $tools->execute('get_reservation_details', ['reservation_id' => $stay['reservation']->id_reservation]);
        $this->assertTrue($own->ok);
        $this->assertSame('1800.00', $own->payload['montant_total']);
    }

    public function test_tools_outside_the_allowlist_and_invalid_inputs_are_rejected(): void
    {
        $stay = $this->bookedStay();
        $conversation = AiConversation::create([
            'id_utilisateur' => $stay['client']->id_utilisateur,
            'id_reservation' => $stay['reservation']->id_reservation,
        ]);
        $tools = new ToolRegistry(new AssistantContext($stay['client'], $conversation, $stay['reservation']), new BookingPolicy());

        $this->assertSame('unknown_tool', $tools->execute('cancel_reservation', ['reservation_id' => 1])->payload['error']);
        $this->assertSame('unknown_tool', $tools->execute('run_sql', ['query' => 'DELETE FROM reservations'])->payload['error']);
        $this->assertSame('invalid_input', $tools->execute('get_reservation_details', ['reservation_id' => 'abc'])->payload['error']);
        $this->assertDatabaseHas('reservations', ['id_reservation' => $stay['reservation']->id_reservation, 'statut' => 'confirme']);
    }

    public function test_customers_cannot_use_agency_endpoints(): void
    {
        $stay = $this->bookedStay();
        Sanctum::actingAs($stay['client']);

        $this->getJson('/api/agency/assistant/dashboard')->assertForbidden();
    }

    public function test_an_agency_only_sees_conversations_about_its_own_accommodations(): void
    {
        $stay = $this->bookedStay();
        Sanctum::actingAs($stay['client']);
        $conversationId = $this->postJson('/api/assistant/conversations', ['id_reservation' => $stay['reservation']->id_reservation])
            ->json('data.id');

        [$otherAgencyAccount] = $this->makeAgency('Sahara Tours');

        Sanctum::actingAs($otherAgencyAccount);
        $this->getJson("/api/agency/assistant/conversations/{$conversationId}")->assertForbidden();
        $this->postJson("/api/agency/assistant/conversations/{$conversationId}/messages", ['content' => 'Bonjour'])->assertForbidden();
        $this->getJson('/api/agency/assistant/dashboard')->assertOk()->assertJsonPath('data.stats.upcoming_total', 0);

        Sanctum::actingAs($stay['agencyAccount']);
        $this->getJson("/api/agency/assistant/conversations/{$conversationId}")->assertOk();
    }

    public function test_an_agency_account_without_an_owned_agency_is_refused(): void
    {
        [$account, $agency] = $this->makeAgency();
        $agency->forceFill(['id_utilisateur' => null])->save();

        Sanctum::actingAs($account->fresh());

        $this->getJson('/api/agency/assistant/dashboard')->assertForbidden();
    }
}
