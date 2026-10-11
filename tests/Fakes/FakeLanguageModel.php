<?php

namespace Tests\Fakes;

use App\Services\Ai\ChatResult;
use App\Services\Ai\Contracts\LanguageModel;
use App\Services\Ai\Tools\ToolRegistry;
use Closure;

/**
 * Scriptable LanguageModel for tests: simulates LLM behaviour (tool calls, malformed
 * structured output, failures) without any network access.
 */
class FakeLanguageModel implements LanguageModel
{
    public int $chatCalls = 0;

    /** @var list<string> */
    public array $systemPrompts = [];

    public function __construct(
        public ?Closure $onChat = null,
        public ?Closure $onExtract = null,
        public ?Closure $onWrite = null,
    ) {
    }

    public function provider(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-model';
    }

    public function chat(string $system, array $history, ToolRegistry $tools, array $hints = []): ChatResult
    {
        $this->chatCalls++;
        $this->systemPrompts[] = $system;

        return $this->onChat
            ? ($this->onChat)($system, $history, $tools, $hints)
            : new ChatResult('Réponse simulée.');
    }

    public function extract(string $task, string $system, string $input, array $schema, array $context = []): array
    {
        return $this->onExtract
            ? ($this->onExtract)($task, $input, $context)
            : ['intention' => 'none', 'confidence' => 0.9, 'language' => 'fr'];
    }

    public function write(string $system, string $instruction): ?string
    {
        return $this->onWrite ? ($this->onWrite)($instruction) : null;
    }
}
