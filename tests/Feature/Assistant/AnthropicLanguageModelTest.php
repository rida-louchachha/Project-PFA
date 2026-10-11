<?php

namespace Tests\Feature\Assistant;

use Anthropic\Client;
use Anthropic\RequestOptions;
use App\Models\Models\AiConversation;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Intent\IntentClassifier;
use App\Services\Ai\Prompts;
use App\Services\Ai\Providers\AnthropicLanguageModel;
use App\Services\Ai\Tools\AssistantContext;
use App\Services\Ai\Tools\ToolRegistry;
use App\Services\Booking\BookingPolicy;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the real Anthropic SDK client offline: requests go to a mocked HTTP
 * transport, so we can assert the exact payload and replay canned API responses.
 */
class AnthropicLanguageModelTest extends TestCase
{
    use BuildsBookings;
    use RefreshDatabase;

    /** @var list<array{request: \Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    private function model(array $responses): AnthropicLanguageModel
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $client = new Client(
            apiKey: 'sk-ant-test',
            requestOptions: RequestOptions::with(maxRetries: 0, transporter: new GuzzleClient(['handler' => $stack])),
        );

        return new AnthropicLanguageModel([
            'api_key' => 'sk-ant-test',
            'model' => 'claude-opus-5-5',
            'effort' => 'low',
            'max_tokens' => 8000,
            'timeout' => 30,
            'max_retries' => 0,
            'fallbacks' => true,
        ], maxToolRounds: 4, client: $client);
    }

    private static function message(array $content, string $stopReason): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_' . uniqid(),
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => $content,
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 120, 'output_tokens' => 40],
        ]));
    }

    private function body(int $index): array
    {
        return json_decode((string) $this->history[$index]['request']->getBody(), true);
    }

    public function test_tool_loop_request_shape_and_answer(): void
    {
        $stay = $this->bookedStay();
        $conversation = AiConversation::create([
            'id_utilisateur' => $stay['client']->id_utilisateur,
            'id_reservation' => $stay['reservation']->id_reservation,
        ]);
        $context = new AssistantContext($stay['client'], $conversation, $stay['reservation']);
        $tools = new ToolRegistry($context, new BookingPolicy());

        $model = $this->model([
            self::message([[
                'type' => 'tool_use',
                'id' => 'toolu_01',
                'name' => 'get_reservation_details',
                'input' => ['reservation_id' => $stay['reservation']->id_reservation],
            ]], 'tool_use'),
            self::message([['type' => 'text', 'text' => 'Vous avez payé 1800.00 MAD.']], 'end_turn'),
        ]);

        $result = $model->chat(Prompts::assistantSystem($context), [['role' => 'user', 'content' => 'Combien ai-je payé ?']], $tools);

        $this->assertSame('Vous avez payé 1800.00 MAD.', $result->text);
        $this->assertSame(['input_tokens' => 240, 'output_tokens' => 80], $result->usage);
        $this->assertCount(2, $this->history);

        // First request: model, effort, strict allowlisted tools, refusal fallbacks
        $first = $this->body(0);
        $this->assertSame('claude-opus-5-5', $first['model']);
        $this->assertSame('low', $first['output_config']['effort']);
        $this->assertSame('default', $first['fallbacks']);
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $this->history[0]['request']->getHeaderLine('anthropic-beta'));
        $this->assertSame(['list_my_reservations', 'get_reservation_details', 'get_cancellation_policy', 'request_human_assistance'], array_column($first['tools'], 'name'));
        $this->assertTrue($first['tools'][1]['strict']);
        $this->assertArrayNotHasKey('thinking', $first);
        $this->assertSame('sk-ant-test', $this->history[0]['request']->getHeaderLine('x-api-key'));

        // Second request: append-only loop, tool result computed server-side from MySQL
        $second = $this->body(1);
        $this->assertSame('assistant', $second['messages'][1]['role']);
        $this->assertSame('tool_use', $second['messages'][1]['content'][0]['type']);
        $toolResult = $second['messages'][2]['content'][0];
        $this->assertSame('tool_result', $toolResult['type']);
        $this->assertSame('toolu_01', $toolResult['tool_use_id']);
        $this->assertSame('1800.00', json_decode($toolResult['content'], true)['montant_total']);
    }

    public function test_structured_intention_output(): void
    {
        $model = $this->model([
            self::message([['type' => 'text', 'text' => '{"intention":"confirmed","confidence":0.94,"language":"fr"}']], 'end_turn'),
        ]);

        $raw = $model->extract('arrival_intention', Prompts::INTENTION_CLASSIFIER, 'Oui, je confirme', IntentClassifier::SCHEMA);

        $this->assertSame('confirmed', $raw['intention']);
        $format = $this->body(0)['output_config']['format'];
        $this->assertSame('json_schema', $format['type']);
        $this->assertSame(IntentClassifier::INTENTIONS, $format['schema']['properties']['intention']['enum']);
        $this->assertFalse($format['schema']['additionalProperties']);
    }

    public function test_api_errors_become_provider_exceptions(): void
    {
        $model = $this->model([
            new Response(429, ['Content-Type' => 'application/json'], json_encode([
                'type' => 'error',
                'error' => ['type' => 'rate_limit_error', 'message' => 'Rate limited'],
            ])),
        ]);

        try {
            $model->write(Prompts::OUTBOUND_WRITER, 'test');
            $this->fail('Expected an AiProviderException');
        } catch (AiProviderException $e) {
            $this->assertSame('rate_limited', $e->reason);
            $this->assertTrue($e->retryable);
        }
    }
}
