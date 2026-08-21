<?php

namespace App\AI\Workflows;

use App\AI\MessagesResponse;

final class WorkflowRun
{
    /**
     * @param  array<int, WorkflowStep>  $steps
     */
    public function __construct(
        public readonly array $steps,
        public readonly ?int $ticketId,
        public readonly ?MessagesResponse $summary,
    ) {}
}
