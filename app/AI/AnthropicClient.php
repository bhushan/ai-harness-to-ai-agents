<?php

namespace App\AI;

use App\AI\Transport\LlmTransport;

/**
 * Turns a MessagesRequest into a MessagesResponse.
 *
 * The client owns the model and the token ceiling; the caller owns the
 * conversation. Which transport is underneath is not its business, and in this
 * repository the only transport reads fixtures.
 */
final class AnthropicClient
{
    public function __construct(
        private readonly LlmTransport $transport,
        private readonly string $model,
        private readonly int $maxTokens,
    ) {}

    public function send(string $scenario, MessagesRequest $request): MessagesResponse
    {
        $payload = $request->toArray($this->model, $this->maxTokens);

        return new MessagesResponse($payload, $this->transport->send($scenario, $payload));
    }
}
