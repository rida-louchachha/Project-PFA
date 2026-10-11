<?php

/*
|--------------------------------------------------------------------------
| TravelPlan AI — Assistant Intelligent de Réservation
|--------------------------------------------------------------------------
|
| All LLM credentials stay on the backend. When no API key is configured the
| assistant runs in "demo" mode: a deterministic, rule-based engine that uses
| the same secure tools and the same database, so the feature stays demonstrable
| offline. The demo engine is also the automatic fallback when the API fails.
|
*/

return [

    // anthropic | demo
    'provider' => env('AI_PROVIDER', env('ANTHROPIC_API_KEY') ? 'anthropic' : 'demo'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AI_MODEL', 'claude-opus-5-5'),
        // low | medium | high — chat and classification are short, latency-sensitive calls
        'effort' => env('AI_EFFORT', 'low'),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 8000),
        'timeout' => (float) env('AI_TIMEOUT_SECONDS', 30),
        'max_retries' => (int) env('AI_MAX_RETRIES', 1),
        // Server-side refusal fallback (beta "server-side-fallback-2026-07-01")
        'fallbacks' => (bool) env('AI_REFUSAL_FALLBACKS', true),
    ],

    'chat' => [
        'max_message_length' => 1000,
        // Messages of history sent to the model (older ones are dropped)
        'history_messages' => 20,
        // Upper bound on tool round-trips per customer message
        'max_tool_rounds' => 4,
        'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 12),
    ],

    'intention' => [
        // Below this confidence the classifier result is not recorded
        'min_confidence' => (float) env('AI_INTENTION_MIN_CONFIDENCE', 0.6),
    ],

    // Automated communications (Laravel Scheduler: travelplan:send-reminders)
    'reminders' => [
        'intention_request_hours_before' => (int) env('AI_INTENTION_REQUEST_HOURS_BEFORE', 72),
        'followup_after_hours' => (int) env('AI_FOLLOWUP_AFTER_HOURS', 24),
        'arrival_reminder_hours_before' => (int) env('AI_ARRIVAL_REMINDER_HOURS_BEFORE', 24),
        // Anti-spam: minimum gap between two automated messages for the same reservation
        'min_hours_between_messages' => (int) env('AI_MIN_HOURS_BETWEEN_MESSAGES', 6),
    ],

    // Angular SPA base URL, used in email links
    'frontend_url' => rtrim(env('FRONTEND_URL', 'http://localhost:4200'), '/'),
];
