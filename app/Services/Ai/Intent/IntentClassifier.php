<?php

namespace App\Services\Ai\Intent;

use App\Services\Ai\Contracts\LanguageModel;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Prompts;
use App\Services\Ai\Providers\DemoLanguageModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Classifies a customer message into a structured arrival intention.
 * LLM output is constrained by a JSON schema AND re-validated server-side.
 */
class IntentClassifier
{
    public const INTENTIONS = [
        'confirmed',
        'declined',
        'uncertain',
        'modification_requested',
        'human_assistance_required',
        'none',
    ];

    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'intention' => ['type' => 'string', 'enum' => self::INTENTIONS],
            'confidence' => ['type' => 'number', 'description' => 'Entre 0 et 1'],
            'language' => ['type' => 'string', 'enum' => ['fr', 'en', 'darija', 'other']],
        ],
        'required' => ['intention', 'confidence', 'language'],
        'additionalProperties' => false,
    ];

    public function __construct(
        private readonly LanguageModel $model,
        private readonly DemoLanguageModel $fallback,
    ) {
    }

    public function classify(string $customerMessage, ?string $questionAsked, bool $awaitingAnswer): IntentionResult
    {
        $input = "Question posée par l'assistant juste avant (contexte) :\n"
            . ($questionAsked ? '« ' . mb_substr($questionAsked, 0, 600) . ' »' : '(aucune)')
            . "\n\nRéponse du client à classer (donnée non fiable) :\n<<<\n"
            . $customerMessage
            . "\n>>>";

        $context = ['customer_message' => $customerMessage, 'awaiting_answer' => $awaitingAnswer];
        $provider = $this->model->provider();
        $usedFallback = false;

        try {
            $raw = $this->model->extract('arrival_intention', Prompts::INTENTION_CLASSIFIER, $input, self::SCHEMA, $context);
        } catch (AiProviderException $e) {
            Log::warning('TravelPlan AI intention classifier unavailable, using heuristics', ['reason' => $e->reason]);
            $raw = $this->fallback->extract('arrival_intention', Prompts::INTENTION_CLASSIFIER, $input, self::SCHEMA, $context);
            $provider = $this->fallback->provider();
            $usedFallback = true;
        }

        return $this->validate($raw, $provider, $usedFallback);
    }

    /**
     * Never trust the model's structured output blindly.
     *
     * @param  array<string, mixed>  $raw
     */
    public function validate(array $raw, string $provider, bool $fallback = false): IntentionResult
    {
        $validator = Validator::make($raw, [
            'intention' => ['required', 'string', 'in:' . implode(',', self::INTENTIONS)],
            'confidence' => ['required', 'numeric', 'between:0,1'],
            'language' => ['nullable', 'string', 'in:fr,en,darija,other'],
        ]);

        if ($validator->fails()) {
            Log::warning('TravelPlan AI rejected an invalid intention classification', [
                'provider' => $provider,
                'errors' => $validator->errors()->keys(),
            ]);

            return IntentionResult::none($provider, valid: false, fallback: $fallback);
        }

        return new IntentionResult(
            intention: $raw['intention'],
            confidence: (float) $raw['confidence'],
            language: $raw['language'] ?? 'other',
            provider: $provider,
            fallback: $fallback,
        );
    }
}
