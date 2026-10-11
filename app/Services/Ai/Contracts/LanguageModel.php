<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\ChatResult;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Tools\ToolRegistry;

/**
 * Provider abstraction for TravelPlan AI.
 *
 * Implementations never touch the database directly: every data access goes
 * through the ToolRegistry, which enforces the allowlist and ownership checks.
 */
interface LanguageModel
{
    /** Provider identifier stored in message audit metadata (anthropic, demo, ...). */
    public function provider(): string;

    public function model(): string;

    /**
     * Run one assistant turn, including any tool round-trips.
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $history  Oldest first, last item is the customer message.
     * @param  array{recorded_intention?: string|null}  $hints  Server-side facts about this turn (already present in $system for LLMs).
     *
     * @throws AiProviderException
     */
    public function chat(string $system, array $history, ToolRegistry $tools, array $hints = []): ChatResult;

    /**
     * Structured extraction constrained by a JSON schema. The caller validates the result.
     *
     * @param  string  $task  Task identifier (e.g. "arrival_intention"), used by non-LLM providers.
     * @param  array<string, mixed>  $context  Extra structured context for non-LLM providers.
     * @return array<string, mixed>
     *
     * @throws AiProviderException
     */
    public function extract(string $task, string $system, string $input, array $schema, array $context = []): array;

    /**
     * Free-text generation (short personalised paragraphs). Returns null when the provider
     * does not generate text (demo mode), so the caller uses its template.
     *
     * @throws AiProviderException
     */
    public function write(string $system, string $instruction): ?string;
}
