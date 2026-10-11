<?php

namespace App\Providers;

use App\Events\ReservationPaymentSettled;
use App\Jobs\SendBookingCommunication;
use App\Models\Models\AiConversation;
use App\Models\Models\BookingNotification;
use App\Policies\AiConversationPolicy;
use App\Services\Ai\AssistantService;
use App\Services\Ai\Communication\CommunicationEligibility;
use App\Services\Ai\Communication\MessageComposer;
use App\Services\Ai\Contracts\LanguageModel;
use App\Services\Ai\Intent\ArrivalIntentionService;
use App\Services\Ai\Providers\AnthropicLanguageModel;
use App\Services\Ai\Providers\DemoLanguageModel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // TravelPlan AI provider abstraction (config/ai.php)
        $this->app->bind(LanguageModel::class, function ($app) {
            return match (config('ai.provider')) {
                'anthropic' => new AnthropicLanguageModel(
                    config('ai.anthropic'),
                    (int) config('ai.chat.max_tool_rounds'),
                ),
                default => $app->make(DemoLanguageModel::class),
            };
        });

        $this->app->bind(ArrivalIntentionService::class, fn () => new ArrivalIntentionService(
            (float) config('ai.intention.min_confidence'),
        ));

        $this->app->bind(CommunicationEligibility::class, fn () => new CommunicationEligibility(config('ai.reminders')));

        $this->app->bind(MessageComposer::class, fn ($app) => new MessageComposer(
            $app->make(LanguageModel::class),
            config('ai.frontend_url'),
        ));

        $this->app->when(AssistantService::class)
            ->needs('$historyLimit')
            ->give(fn () => (int) config('ai.chat.history_messages'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(AiConversation::class, AiConversationPolicy::class);

        // Per-user throttle on AI chat messages (LLM cost + abuse protection)
        RateLimiter::for('assistant', function (Request $request) {
            return Limit::perMinute((int) config('ai.chat.rate_limit_per_minute'))
                ->by('assistant:' . ($request->user()?->id_utilisateur ?? $request->ip()));
        });

        // Public AI smart search (calls the LLM): per-IP throttle
        RateLimiter::for('smart-search', function (Request $request) {
            return Limit::perMinute((int) config('ai.chat.rate_limit_per_minute') * 2)->by('smart-search:' . $request->ip());
        });

        // Successful payment => queued booking confirmation (idempotent job)
        Event::listen(function (ReservationPaymentSettled $event) {
            SendBookingCommunication::dispatch($event->reservationId, BookingNotification::TYPE_BOOKING_CONFIRMATION)
                ->afterCommit();
        });
    }
}
