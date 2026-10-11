<?php

namespace Tests\Feature\Assistant;

use App\Jobs\SendBookingCommunication;
use App\Models\Models\BookingNotification;
use App\Models\Models\ReservationConfirmation;
use App\Notifications\BookingAssistantNotification;
use App\Services\Ai\Communication\BookingCommunicationService;
use App\Services\Ai\Contracts\LanguageModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeLanguageModel;
use Tests\TestCase;

class BookingCommunicationTest extends TestCase
{
    use BuildsBookings;
    use RefreshDatabase;

    private function service(): BookingCommunicationService
    {
        return $this->app->make(BookingCommunicationService::class);
    }

    public function test_a_successful_payment_queues_the_booking_confirmation(): void
    {
        Queue::fake();
        $client = $this->makeClient();
        [, $agency] = $this->makeAgency();
        $reservation = $this->makeReservation($client, $this->makeLogement($agency), ['statut' => 'en_attente']);

        Sanctum::actingAs($client);
        $this->postJson('/api/payments', [
            'id_reservation' => $reservation->id_reservation,
            'mode' => 'en_ligne',
            'montant' => 1800,
        ])->assertCreated();

        Queue::assertPushed(SendBookingCommunication::class, fn ($job) => $job->reservationId === $reservation->id_reservation
            && $job->type === BookingNotification::TYPE_BOOKING_CONFIRMATION);
    }

    public function test_the_end_to_end_confirmation_creates_one_email_and_one_chat_message(): void
    {
        Notification::fake();
        $client = $this->makeClient();
        [, $agency] = $this->makeAgency();
        $reservation = $this->makeReservation($client, $this->makeLogement($agency), ['statut' => 'en_attente']);

        // Real payment flow, sync queue (phpunit.xml): payment -> event -> job -> email + conversation
        Sanctum::actingAs($client);
        $this->postJson('/api/payments', ['id_reservation' => $reservation->id_reservation, 'mode' => 'en_ligne', 'montant' => 1800])
            ->assertCreated();

        Notification::assertSentTo($client, BookingAssistantNotification::class, function ($notification) use ($reservation) {
            return $notification->type === 'booking_confirmation'
                && str_contains($notification->message->body, 'est confirmée et votre paiement de 1800.00 DH est enregistré')
                && str_ends_with($notification->message->actionUrl, "/assistant/{$reservation->id_reservation}");
        });

        $this->assertDatabaseHas('booking_notifications', [
            'id_reservation' => $reservation->id_reservation,
            'type' => 'booking_confirmation',
            'statut' => 'sent',
        ]);
        $this->assertDatabaseHas('ai_messages', ['kind' => 'booking_confirmation', 'role' => 'assistant']);

        // Dispatched again (retry, double event...) => nothing new is sent
        $this->assertSame('duplicate', $this->service()->send($reservation->id_reservation, 'booking_confirmation'));
        Notification::assertSentToTimes($client, BookingAssistantNotification::class, 1);
        $this->assertDatabaseCount('ai_messages', 1);
    }

    public function test_nothing_is_claimed_for_an_unpaid_reservation(): void
    {
        Notification::fake();
        $client = $this->makeClient();
        [, $agency] = $this->makeAgency();
        $reservation = $this->makeReservation($client, $this->makeLogement($agency), ['statut' => 'confirme']);
        $this->pay($reservation, 'en_attente');

        $this->assertSame('skipped:payment_not_settled', $this->service()->send($reservation->id_reservation, 'booking_confirmation'));
        Notification::assertNothingSent();
        $this->assertDatabaseCount('booking_notifications', 0);
    }

    public function test_an_ai_sentence_containing_facts_is_replaced_by_the_template(): void
    {
        Notification::fake();
        $this->app->instance(LanguageModel::class, new FakeLanguageModel(
            onWrite: fn () => 'Votre paiement de 999 MAD pour le 5 juin est validé.',
        ));
        $stay = $this->bookedStay();

        $this->assertSame('sent', $this->service()->send($stay['reservation']->id_reservation, 'booking_confirmation'));

        Notification::assertSentTo($stay['client'], BookingAssistantNotification::class, fn ($n) => $n->message->personalLine === null);
        $this->assertDatabaseHas('booking_notifications', ['content_source' => 'template']);
    }

    public function test_a_clean_ai_sentence_is_used_for_personalisation(): void
    {
        Notification::fake();
        $this->app->instance(LanguageModel::class, new FakeLanguageModel(
            onWrite: fn () => 'Marrakech vous attend avec ses jardins et sa lumière dorée.',
        ));
        $stay = $this->bookedStay();

        $this->service()->send($stay['reservation']->id_reservation, 'booking_confirmation');

        Notification::assertSentTo($stay['client'], BookingAssistantNotification::class,
            fn ($n) => $n->message->personalLine === 'Marrakech vous attend avec ses jardins et sa lumière dorée.');
        $this->assertDatabaseHas('booking_notifications', ['content_source' => 'ai']);
    }

    public function test_the_scheduler_requests_intentions_before_check_in_and_skips_cancelled_stays(): void
    {
        Notification::fake();
        $soon = $this->bookedStay(['date_debut' => Carbon::now()->addHours(48), 'date_fin' => Carbon::now()->addHours(96)]);
        $later = $this->bookedStay(['date_debut' => Carbon::now()->addDays(20), 'date_fin' => Carbon::now()->addDays(22)]);
        $cancelled = $this->bookedStay(['statut' => 'annule', 'date_debut' => Carbon::now()->addHours(20), 'date_fin' => Carbon::now()->addHours(60)]);

        $this->artisan('travelplan:send-reminders')->assertSuccessful();

        Notification::assertSentTo($soon['client'], BookingAssistantNotification::class, fn ($n) => $n->type === 'intention_request');
        Notification::assertNotSentTo($later['client'], BookingAssistantNotification::class);
        Notification::assertNotSentTo($cancelled['client'], BookingAssistantNotification::class);

        $this->assertDatabaseHas('reservation_confirmations', [
            'id_reservation' => $soon['reservation']->id_reservation,
            'intention' => 'pending',
        ]);
        $this->assertNotNull(ReservationConfirmation::first()->requested_at);

        // Running the scheduler again does not send the same request twice
        $this->artisan('travelplan:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($soon['client'], BookingAssistantNotification::class, 1);
    }

    public function test_follow_up_after_silence_and_no_arrival_reminder_for_declined_stays(): void
    {
        Notification::fake();
        $silent = $this->bookedStay(['date_debut' => Carbon::now()->addHours(30), 'date_fin' => Carbon::now()->addHours(80)]);
        $declined = $this->bookedStay(['date_debut' => Carbon::now()->addHours(10), 'date_fin' => Carbon::now()->addHours(50)]);

        foreach ([$silent, $declined] as $stay) {
            BookingNotification::create([
                'id_reservation' => $stay['reservation']->id_reservation,
                'type' => 'intention_request',
                'statut' => 'sent',
                'sent_at' => Carbon::now()->subHours(26),
            ]);
        }
        ReservationConfirmation::create(['id_reservation' => $silent['reservation']->id_reservation, 'intention' => 'pending', 'requested_at' => Carbon::now()->subHours(26)]);
        ReservationConfirmation::create(['id_reservation' => $declined['reservation']->id_reservation, 'intention' => 'declined', 'requested_at' => Carbon::now()->subHours(26), 'responded_at' => Carbon::now()->subHours(25)]);

        $this->artisan('travelplan:send-reminders')->assertSuccessful();

        Notification::assertSentTo($silent['client'], BookingAssistantNotification::class, fn ($n) => $n->type === 'intention_followup');
        Notification::assertNotSentTo($declined['client'], BookingAssistantNotification::class);
    }

    public function test_anti_spam_gap_between_two_automated_messages(): void
    {
        Notification::fake();
        $stay = $this->bookedStay(['date_debut' => Carbon::now()->addHours(20), 'date_fin' => Carbon::now()->addHours(70)]);

        $this->assertSame('sent', $this->service()->send($stay['reservation']->id_reservation, 'intention_request'));
        // Arrival reminder is also due (< 24h) but the customer was contacted minutes ago
        $this->assertSame('skipped:recently_contacted', $this->service()->send($stay['reservation']->id_reservation, 'arrival_reminder'));

        $this->travel(7)->hours();
        $this->assertSame('sent', $this->service()->send($stay['reservation']->id_reservation, 'arrival_reminder'));
    }
}
