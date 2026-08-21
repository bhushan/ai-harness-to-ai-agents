<?php

namespace App\AI;

/**
 * Builds an Anthropic Messages API request payload.
 *
 * Nothing here is demo specific. This is the same body you would POST to
 * /v1/messages in production, which is exactly why it is worth putting on
 * screen: the shape of the payload is the shape of the lesson.
 */
final class MessagesRequest
{
    private ?string $system = null;

    /** @var array<int, array<string, mixed>> */
    private array $messages = [];

    public static function make(): self
    {
        return new self;
    }

    public function withSystem(string $system): self
    {
        $clone = clone $this;
        $clone->system = $system;

        return $clone;
    }

    public function withUserMessage(string $content): self
    {
        return $this->withMessage('user', $content);
    }

    /**
     * @param  string|array<int, array<string, mixed>>  $content
     */
    public function withMessage(string $role, string|array $content): self
    {
        $clone = clone $this;
        $clone->messages[] = ['role' => $role, 'content' => $content];

        return $clone;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $model, int $maxTokens): array
    {
        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
        ];

        // Absent rather than null. An empty system prompt is not the same thing
        // as no system prompt, and the audience should see the difference.
        if ($this->system !== null) {
            $payload['system'] = $this->system;
        }

        $payload['messages'] = $this->messages;

        return $payload;
    }
}
