<?php

namespace App\AI;

/**
 * A real Anthropic Messages API response, plus the request that produced it.
 *
 * The request is carried along so a command can show precisely what went out
 * next to what came back, with no chance of the two drifting apart.
 */
final class MessagesResponse
{
    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        private readonly array $request,
        private readonly array $raw,
    ) {}

    /** @return array<string, mixed> */
    public function request(): array
    {
        return $this->request;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * Every text block, joined. Assistant turns can hold more than one block.
     */
    public function text(): string
    {
        $parts = [];

        foreach ($this->raw['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $parts[] = $block['text'];
            }
        }

        return trim(implode("\n\n", $parts));
    }

    public function stopReason(): string
    {
        return $this->raw['stop_reason'] ?? 'unknown';
    }

    public function model(): string
    {
        return $this->raw['model'] ?? 'unknown';
    }

    public function inputTokens(): int
    {
        return (int) ($this->raw['usage']['input_tokens'] ?? 0);
    }

    public function outputTokens(): int
    {
        return (int) ($this->raw['usage']['output_tokens'] ?? 0);
    }

    /**
     * The one line summary printed next to the RESPONSE label.
     */
    public function summary(): string
    {
        return sprintf(
            '%s   stop_reason: %s   %d in / %d out',
            $this->model(),
            $this->stopReason(),
            $this->inputTokens(),
            $this->outputTokens(),
        );
    }
}
