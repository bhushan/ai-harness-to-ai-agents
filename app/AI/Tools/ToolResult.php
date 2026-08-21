<?php

namespace App\AI\Tools;

/**
 * What a tool hands back, on its way to becoming a tool_result block.
 */
final class ToolResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public readonly bool $isError,
        public readonly array $data,
        public readonly string $summary,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function ok(array $data, string $summary = ''): self
    {
        return new self(false, $data, $summary);
    }

    public static function failed(string $message): self
    {
        return new self(true, ['error' => $message], $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * The content of the tool_result block. The API accepts a string here, so
     * this is a compact JSON document: fewer tokens than pretty printing it,
     * and the readable version is on screen a moment earlier anyway.
     */
    public function toContent(): string
    {
        return (string) json_encode(
            $this->data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
