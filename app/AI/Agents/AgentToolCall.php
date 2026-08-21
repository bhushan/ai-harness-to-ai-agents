<?php

namespace App\AI\Agents;

use App\AI\Tools\ToolResult;

final class AgentToolCall
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly string $id,
        public readonly string $tool,
        public readonly array $input,
        public readonly ToolResult $result,
    ) {}
}
