<?php

namespace Tests\Feature\Assistant;

use App\Models\Models\ReservationConfirmation;
use App\Services\Ai\Contracts\LanguageModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeLanguageModel;
use Tests\TestCase;

class ArrivalIntentionTest extends TestCase
{
    use BuildsBookings;
    use RefreshDatabase;

    /** Opens the reservation conversation as the client and marks the arrival question as asked. */
    private function conversationAwaitingAnswer(array $stay): int
    {
        Sanctum::actingAs($stay['client']);
        ReservationConfirmation::create([
            'id_reservation' => $stay['reservation']->id_reservation,
            'intention' => 'pending',
            'source' => 'system',
            'requested_at' => now(),
        ]);

        return $this->postJson('/api/assistant/conversations', ['id_reservation' => $stay['reservation']->id_reservation])
            ->assertOk()
            ->json('data.id');
    }

    public function test_a_natural_confirmation_is_recorded_without_touching_the_booking(): void
    {
        $stay = $this->bookedStay();
        $conversationId = $this->conversationAwaitingAnswer($stay);

        $this->postJson("/api/assistant/conversations/{$conversationId}/messages", ['content' => 'Oui, je confirme.'])
            ->assertOk()
            ->assertJsonPath('data.recorded_intention', 'confirmed')
            ->assertJsonPath('data.conversation.confirmation.intention', 'confirmed');

        $this->assertDatabaseHas('reservation_confirmations', [
            'id_reservation' => $stay['reservation']->id_reservation,
            'intention' => 'confirmed',
            'source' => 'ai_classifier',
        ]);
        $this->assertDatabaseHas('ai_messages', ['role' => 'customer', 'content' => 'Oui, je confirme.', 'intent' => 'confirmed']);
        $this->assertDatabaseHas('reservations', ['id_reservation' => $stay['reservation']->id_reservation, 'statut' => 'confirme']);
    }

    public function test_declining_records_the_intention_but_never_cancels_or_refunds(): void
    {
        $stay = $this->bookedStay();
        $conversationId = $this->conversationAwaitingAnswer($stay);

        $response = $this->postJson("/api/assistant/conversations/{$conversationId}/messages", ['content' => 'Non, je souhaite annuler.'])
            ->assertOk()
            ->assertJsonPath('data.recorded_intention', 'declined');

        $this->assertStringContainsString('pas annulée automatiquement', $response->json('data.assistant_message.content'));
        $this->assertDatabaseHas('reservations', ['id_reservation' => $stay['reservation']->id_reservation, 'statut' => 'confirme']);
        $this->assertDatabaseHas('paiements', ['id_reservation' => $stay['reservation']->id_reservation, 'statut' => 'regle']);
    }

    public function test_modification_and_human_requests_hand_the_conversation_to_the_agency(): void
    {
        $stay = $this->bookedStay();
        $conversationId = $this->conversationAwaitingAnswer($stay);

        $this->postJson("/api/assistant/conversations/{$conversationId}/messages", ['content' => 'Je veux modifier mes dates.'])
            ->assertOk()
            ->assertJsonPath('data.recorded_intention', 'modification_requested')
            ->assertJsonPath('data.conversation.statut', 'handoff');

        $this->assertDatabaseHas('reservations', [
            'id_reservation' => $stay['reservation']->id_reservation,
            'date_debut' => $stay['reservation']->date_debut->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_questions_are_answered_from_real_data_and_not_recorded_as_intentions(): void
    {
        $stay = $this->bookedStay();
        $conversationId = $this->conversationAwaitingAnswer($stay);

        $reply = $this->postJson("/api/assistant/conversations/{$conversationId}/messages", ['content' => 'Combien ai-je payé ?'])
            ->assertOk()
            ->assertJsonPath('data.recorded_intention', null)
            ->json('data.assistant_message.content');

        $this->assertStringContainsString('1800.00 DH', $reply);
        $this->assertDatabaseHas('reservation_confirmations', ['intention' => 'pending']);
    }

    public function test_invalid_structured_output_from_the_model_is_rejected(): void
    {
        $this->app->instance(LanguageModel::class, new FakeLanguageModel(
            onExtract: fn () => ['intention' => 'cancel_and_refund_now', 'confidence' => 3, 'language' => 'fr'],
        ));

        $stay = $this->bookedStay();
        $conversationId = $this->conversationAwaitingAnswer($stay);

        $this->postJson("/api/assistant/conversations/{$conversationId}/messages", ['content' => 'Oui, je confirme'])
            ->assertOk()
            ->assertJsonPath('data.recorded_intention', null);

        $this->assertDatabaseHas('reservation_confirmations', ['intention' => 'pending']);
        $this->assertDatabaseHas('reservations', ['id_reservation' => $stay['reservation']->id_reservation, 'statut' => 'confirme']);
    }

    public function test_low_confidence_classifications_are_not_recorded(): void
    {
        $this->app->instance(LanguageModel::class, new FakeLanguageModel(
            onExtract: fn () => ['intention' => 'confirmed', 'confidence' => 0.3, 'language' => 'fr'],
        ));

        $stay = $this->bookedStay();
        $conversationId = $this->conversationAwaitingAnswer($stay);

        $this->postJson("/api/assistant/conversations/{$conversationId}/messages", ['content' => 'hmm oui peut-être'])
            ->assertOk()
            ->assertJsonPath('data.recorded_intention', null);
    }

    public function test_cancelled_or_past_stays_do_not_collect_intentions(): void
    {
        $stay = $this->bookedStay(['statut' => 'annule']);
        $conversationId = $this->conversationAwaitingAnswer($stay);

        $this->postJson("/api/assistant/conversations/{$conversationId}/messages", ['content' => 'Oui, je confirme.'])
            ->assertOk()
            ->assertJsonPath('data.recorded_intention', null);

        $this->assertDatabaseHas('reservation_confirmations', ['intention' => 'pending']);
    }
}
