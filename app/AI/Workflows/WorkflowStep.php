<?php

namespace App\AI\Workflows;

use App\AI\Tools\ToolResult;

final class WorkflowStep
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly int $number,
        public readonly string $label,
        public readonly string $tool,
        public readonly array $input,
        public readonly ToolResult $result,
    ) {}
}
