<?php

namespace App\Services\Ai;

use App\Models\Models\AiConversation;
use App\Models\Models\AiMessage;
use App\Models\Models\Reservation;
use App\Models\User;
use App\Services\Ai\Contracts\LanguageModel;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Intent\ArrivalIntentionService;
use App\Services\Ai\Intent\IntentClassifier;
use App\Services\Ai\Providers\DemoLanguageModel;
use App\Services\Ai\Support\GroundingGuard;
use App\Services\Ai\Support\ReservationFacts;
use App\Services\Ai\Tools\AssistantContext;
use App\Services\Ai\Tools\ToolRegistry;
use App\Services\Booking\BookingPolicy;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates one TravelPlan AI conversation turn.
 */
class AssistantService
{
    public function __construct(
        private readonly LanguageModel $model,
        private readonly DemoLanguageModel $fallback,
        private readonly IntentClassifier $classifier,
        private readonly ArrivalIntentionService $intentions,
        private readonly BookingPolicy $policy,
        private readonly int $historyLimit = 20,
    ) {
    }

    /**
     * Get (or create) the customer's conversation for a reservation, or the general one.
     * Ownership of $reservation must be checked by the caller.
     */
    public function openConversation(User $user, ?Reservation $reservation, bool $withWelcome = true): AiConversation
    {
        $conversation = AiConversation::firstOrCreate([
            'id_utilisateur' => $user->id_utilisateur,
            'id_reservation' => $reservation?->id_reservation,
        ], [
            'channel' => 'in_app',
            'statut' => AiConversation::STATUT_ACTIVE,
        ]);

        if ($conversation->wasRecentlyCreated && $withWelcome) {
            $this->addAssistantMessage($conversation, $this->welcomeText($user, $reservation), 'welcome', ['provider' => 'template']);
        }

        return $conversation;
    }

    /**
     * @return array{customer: AiMessage, assistant: AiMessage, recorded_intention: string|null}
     */
    public function reply(User $user, AiConversation $conversation, string $content): array
    {
        $content = trim($content);
        $reservation = $conversation->id_reservation
            ? Reservation::with(['logement.agence', 'paiement', 'confirmation'])->find($conversation->id_reservation)
            : null;

        $previousAssistant = $conversation->messages()
            ->whereIn('role', [AiMessage::ROLE_ASSISTANT, AiMessage::ROLE_AGENT])
            ->reorder('id_message', 'desc')
            ->value('content');

        $customerMessage = $conversation->messages()->create([
            'role' => AiMessage::ROLE_CUSTOMER,
            'content' => $content,
            'kind' => 'chat',
        ]);

        // 1. Structured intention detection (only for upcoming, non-cancelled stays)
        $recorded = null;
        if ($this->intentions->isEligible($reservation)) {
            $classification = $this->classifier->classify($content, $previousAssistant, $this->intentions->isAwaitingAnswer($reservation));
            $recorded = $this->intentions->record($reservation, $conversation, $customerMessage, $classification);
            $customerMessage->forceFill(['meta' => ['classification' => $classification->toArray(), 'recorded' => $recorded !== null]])->save();
            $reservation->load('confirmation');
        }

        // 2. Assistant answer, grounded through the allowlisted tools
        $conversation->refresh();
        $context = new AssistantContext($user, $conversation, $reservation);
        $tools = new ToolRegistry($context, $this->policy);
        $system = Prompts::assistantSystem($context, $recorded);
        $history = $this->history($conversation);
        $hints = ['recorded_intention' => $recorded];

        $started = microtime(true);
        $provider = $this->model;
        $fallbackReason = null;

        try {
            $result = $this->model->chat($system, $history, $tools, $hints);
            if (!$result->hasText()) {
                throw new AiProviderException('The model returned no text (' . $result->stopReason . ').', $result->stopReason);
            }
        } catch (\Throwable $e) {
            $fallbackReason = $e instanceof AiProviderException ? $e->reason : 'unexpected_error';
            Log::warning('TravelPlan AI provider failed, using rule-based fallback', [
                'provider' => $this->model->provider(),
                'reason' => $fallbackReason,
                'error' => $e->getMessage(),
                'conversation' => $conversation->id_conversation,
            ]);
            $provider = $this->fallback;
            $result = $this->fallback->chat($system, $history, $tools, $hints);
        }

        $text = (string) $result->text;
        $ungrounded = GroundingGuard::ungroundedNumbers($text, [
            ...$tools->outputs(),
            $system,
            ...array_column($history, 'content'),
        ]);

        if ($ungrounded !== []) {
            Log::warning('TravelPlan AI reply contains numbers not found in the retrieved data', [
                'conversation' => $conversation->id_conversation,
                'numbers' => $ungrounded,
            ]);
        }

        $assistantMessage = $this->addAssistantMessage($conversation, $text, 'chat', [
            'provider' => $provider->provider(),
            'model' => $result->servedByModel ?? $provider->model(),
            'fallback' => $fallbackReason !== null,
            'fallback_reason' => $fallbackReason,
            'tools' => $tools->calls(),
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'usage' => $result->usage,
            'ungrounded_numbers' => $ungrounded,
        ]);

        return [
            'customer' => $customerMessage->fresh(),
            'assistant' => $assistantMessage,
            'recorded_intention' => $recorded,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function addAssistantMessage(AiConversation $conversation, string $content, string $kind, array $meta = []): AiMessage
    {
        $message = $conversation->messages()->create([
            'role' => AiMessage::ROLE_ASSISTANT,
            'content' => $content,
            'kind' => $kind,
            'meta' => $meta,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    /**
     * Recent history in provider-neutral form. Human agent replies are labelled so the
     * model never presents them as its own words.
     *
     * @return list<array{role: 'user'|'assistant', content: string}>
     */
    private function history(AiConversation $conversation): array
    {
        $messages = $conversation->messages()
            ->reorder('id_message', 'desc')
            ->limit($this->historyLimit)
            ->get()
            ->reverse()
            ->values();

        $history = [];
        foreach ($messages as $message) {
            $history[] = match ($message->role) {
                AiMessage::ROLE_CUSTOMER => ['role' => 'user', 'content' => $message->content],
                AiMessage::ROLE_AGENT => ['role' => 'assistant', 'content' => "[Message d'un conseiller humain de l'agence] " . $message->content],
                default => ['role' => 'assistant', 'content' => $message->content],
            };
        }

        // The Messages API requires the first turn to come from the user
        if ($history !== [] && $history[0]['role'] !== 'user') {
            array_unshift($history, ['role' => 'user', 'content' => '(Le client ouvre la conversation TravelPlan AI.)']);
        }

        return $history;
    }

    private function welcomeText(User $user, ?Reservation $reservation): string
    {
        if ($reservation) {
            $facts = ReservationFacts::details($reservation);
            $title = $facts['logement']['titre'] ?? 'votre logement';

            return "Bonjour {$user->prenom} ! Je suis TravelPlan AI, l'assistant intelligent de TravelPlan. "
                . "Je peux répondre à vos questions sur votre réservation n°{$reservation->id_reservation} « {$title} » : dates, adresse, paiement, conditions d'annulation, ou vous mettre en relation avec l'agence.";
        }

        return "Bonjour {$user->prenom} ! Je suis TravelPlan AI, l'assistant intelligent de TravelPlan. "
            . "Posez-moi vos questions sur vos réservations : dates, paiement, adresse ou conditions d'annulation.";
    }
}
