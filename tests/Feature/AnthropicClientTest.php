<?php

namespace Tests\Feature;

use App\AI\AnthropicClient;
use App\AI\MessagesRequest;
use App\AI\Transport\LlmTransport;
use Tests\TestCase;

class AnthropicClientTest extends TestCase
{
    public function test_it_builds_a_real_messages_api_payload(): void
    {
        $transport = $this->recordingTransport();

        $this->client($transport)->send('llm-raw', MessagesRequest::make()
            ->withUserMessage('Why was I charged twice?'));

        $payload = $transport->lastRequest;

        $this->assertSame(config('demo.model'), $payload['model']);
        $this->assertSame(config('demo.max_tokens'), $payload['max_tokens']);
        $this->assertSame(
            [['role' => 'user', 'content' => 'Why was I charged twice?']],
            $payload['messages']
        );
    }

    public function test_a_request_with_no_context_carries_no_system_prompt_and_no_tools(): void
    {
        $transport = $this->recordingTransport();

        $this->client($transport)->send('llm-raw', MessagesRequest::make()
            ->withUserMessage('Why was I charged twice?'));

        $this->assertArrayNotHasKey('system', $transport->lastRequest);
        $this->assertArrayNotHasKey('tools', $transport->lastRequest);
    }

    public function test_it_reads_the_response_the_way_the_real_api_returns_it(): void
    {
        $response = $this->client($this->recordingTransport())
            ->send('llm-raw', MessagesRequest::make()->withUserMessage('Hello'));

        $this->assertSame('Sorry about that.', $response->text());
        $this->assertSame('end_turn', $response->stopReason());
        $this->assertSame(42, $response->outputTokens());
        $this->assertSame('Hello', $response->request()['messages'][0]['content']);
    }

    private function client(LlmTransport $transport): AnthropicClient
    {
        return new AnthropicClient($transport, config('demo.model'), config('demo.max_tokens'));
    }

    private function recordingTransport(): LlmTransport
    {
        return new class implements LlmTransport
        {
            public array $lastRequest = [];

            public function send(string $scenario, array $request): array
            {
                $this->lastRequest = $request;

                return [
                    'id' => 'msg_01Test',
                    'type' => 'message',
                    'role' => 'assistant',
                    'model' => 'claude-opus-5',
                    'content' => [['type' => 'text', 'text' => 'Sorry about that.']],
                    'stop_reason' => 'end_turn',
                    'stop_sequence' => null,
                    'usage' => ['input_tokens' => 11, 'output_tokens' => 42],
                ];
            }

            public function callCount(string $scenario): int
            {
                return 1;
            }
        };
    }
}
