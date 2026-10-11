<?php

namespace App\Services\Ai\Providers;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\APITimeoutException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\RequestOptions;
use App\Services\Ai\ChatResult;
use App\Services\Ai\Contracts\LanguageModel;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Tools\ToolRegistry;

/**
 * Claude (Anthropic Messages API) implementation, using the official PHP SDK.
 * The API key is read from the backend configuration only.
 */
class AnthropicLanguageModel implements LanguageModel
{
    /**
     * @param  array{api_key: ?string, model: string, effort: string, max_tokens: int, timeout: float, max_retries: int, fallbacks: bool}  $config
     * @param  Client|null  $client  Injected in tests (mock HTTP transport); built lazily otherwise
     */
    public function __construct(
        private readonly array $config,
        private readonly int $maxToolRounds = 4,
        private ?Client $client = null,
    ) {
    }

    public function provider(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return $this->config['model'];
    }

    public function chat(string $system, array $history, ToolRegistry $tools, array $hints = []): ChatResult
    {
        // $hints are already rendered into $system by Prompts::assistantSystem()
        $messages = $history;
        $toolDefinitions = array_map(fn (array $tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'inputSchema' => $tool['input_schema'],
            'strict' => true,
        ], $tools->definitions());

        $usage = ['input_tokens' => 0, 'output_tokens' => 0];

        for ($round = 0; ; $round++) {
            // Last round: no more tool calls allowed, the model must answer with what it has
            $toolChoice = $round >= $this->maxToolRounds ? ['type' => 'none'] : ['type' => 'auto'];

            $response = $this->send(
                system: $system,
                messages: $messages,
                tools: $toolDefinitions,
                toolChoice: $toolChoice,
            );

            $usage['input_tokens'] += $response->usage->inputTokens;
            $usage['output_tokens'] += $response->usage->outputTokens;

            if ($response->stopReason === 'refusal') {
                return new ChatResult(null, 'refusal', $usage, $response->model);
            }

            $toolUses = array_values(array_filter(
                $response->content,
                fn ($block) => $block->type === 'tool_use',
            ));

            if ($response->stopReason !== 'tool_use' || $toolUses === []) {
                return new ChatResult($this->text($response), (string) $response->stopReason, $usage, $response->model);
            }

            // Append-only tool loop: echo the assistant turn, then all tool results in one user turn
            $messages[] = ['role' => 'assistant', 'content' => $this->echoableContent($response->content)];

            $results = [];
            foreach ($toolUses as $block) {
                $input = is_array($block->input ?? null) ? $block->input : [];
                $outcome = $tools->execute($block->name, $input);
                $results[] = [
                    'type' => 'tool_result',
                    'toolUseID' => $block->id,
                    'content' => $outcome->toJson(),
                    'isError' => !$outcome->ok,
                ];
            }
            $messages[] = ['role' => 'user', 'content' => $results];
        }
    }

    public function extract(string $task, string $system, string $input, array $schema, array $context = []): array
    {
        $response = $this->send(
            system: $system,
            messages: [['role' => 'user', 'content' => $input]],
            maxTokens: 2048,
            format: ['type' => 'json_schema', 'schema' => $schema],
        );

        if ($response->stopReason === 'refusal') {
            throw new AiProviderException('The model declined the extraction request.', 'refusal');
        }

        $decoded = json_decode((string) $this->text($response), true);
        if (!is_array($decoded)) {
            throw new AiProviderException('Structured output was not valid JSON.', 'invalid_output');
        }

        return $decoded;
    }

    public function write(string $system, string $instruction): ?string
    {
        $response = $this->send(
            system: $system,
            messages: [['role' => 'user', 'content' => $instruction]],
            maxTokens: 2048,
        );

        return $response->stopReason === 'refusal' ? null : $this->text($response);
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>|null  $tools
     * @param  array<string, mixed>|null  $toolChoice
     * @param  array<string, mixed>|null  $format
     */
    private function send(
        string $system,
        array $messages,
        ?array $tools = null,
        ?array $toolChoice = null,
        ?int $maxTokens = null,
        ?array $format = null,
    ): BetaMessage {
        $outputConfig = ['effort' => $this->config['effort']];
        if ($format !== null) {
            $outputConfig['format'] = $format;
        }

        try {
            return $this->client()->beta->messages->create(
                maxTokens: $maxTokens ?? $this->config['max_tokens'],
                messages: $messages,
                model: $this->config['model'],
                // Opt-in server-side retry on a substitute model when the request is declined by a safety classifier
                fallbacks: $this->config['fallbacks'] ? 'default' : null,
                outputConfig: $outputConfig,
                system: $system,
                toolChoice: $tools ? $toolChoice : null,
                tools: $tools,
                betas: $this->config['fallbacks'] ? ['server-side-fallback-2026-07-01'] : null,
            );
        } catch (AuthenticationException $e) {
            throw new AiProviderException('Anthropic authentication failed (check ANTHROPIC_API_KEY).', 'authentication', false, $e);
        } catch (RateLimitException $e) {
            throw new AiProviderException('Anthropic rate limit reached.', 'rate_limited', true, $e);
        } catch (BadRequestException $e) {
            throw new AiProviderException('Anthropic rejected the request: ' . $e->getMessage(), 'bad_request', false, $e);
        } catch (APIStatusException $e) {
            throw new AiProviderException('Anthropic API error (HTTP ' . ($e->status ?? '?') . ').', 'api_error', ($e->status ?? 500) >= 500, $e);
        } catch (APITimeoutException $e) {
            throw new AiProviderException('Anthropic request timed out.', 'timeout', true, $e);
        } catch (APIConnectionException $e) {
            throw new AiProviderException('Could not reach the Anthropic API.', 'connection', true, $e);
        } catch (APIException $e) {
            throw new AiProviderException('Anthropic SDK error: ' . $e->getMessage(), 'sdk_error', false, $e);
        }
    }

    private function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (empty($this->config['api_key'])) {
            throw new AiProviderException('ANTHROPIC_API_KEY is not configured.', 'not_configured');
        }

        return $this->client ??= new Client(
            apiKey: $this->config['api_key'],
            requestOptions: RequestOptions::with(
                timeout: $this->config['timeout'],
                maxRetries: $this->config['max_retries'],
            ),
        );
    }

    private function text(BetaMessage $response): ?string
    {
        $parts = [];
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $parts[] = $block->text;
            }
        }

        $text = trim(implode("\n", $parts));

        return $text === '' ? null : $text;
    }

    /**
     * After a mid-output fallback, blocks before the last `fallback` marker must not be
     * echoed back except text blocks (API rule). Without a fallback, content is unchanged.
     *
     * @param  list<object>  $content
     * @return list<object>
     */
    private function echoableContent(array $content): array
    {
        $boundary = null;
        foreach ($content as $index => $block) {
            if ($block->type === 'fallback') {
                $boundary = $index;
            }
        }

        if ($boundary === null) {
            return $content;
        }

        $echo = [];
        foreach ($content as $index => $block) {
            if ($index > $boundary || $block->type === 'text') {
                $echo[] = $block;
            }
        }

        return $echo;
    }
}
