<?php

namespace App\AI\Transport;

/**
 * The only way this application talks to a model.
 *
 * There is deliberately no HTTP implementation of this interface in the repo.
 * The demo runs with no network, so the only implementation reads committed
 * fixtures.
 */
interface LlmTransport
{
    /**
     * @param  array<string, mixed>  $request  A real Anthropic Messages API payload.
     * @return array<string, mixed> A real Anthropic Messages API response.
     */
    public function send(string $scenario, array $request): array;

    public function callCount(string $scenario): int;
}
