<?php

namespace App\AI\Agents;

use App\Models\Customer;

/**
 * What the agent is given: a goal, who it concerns, and the limits it runs under.
 *
 * Note what is missing. There is no list of steps.
 */
final class AgentBrief
{
    public function __construct(
        public readonly string $goal,
        public readonly Customer $customer,
        public readonly string $scenario,
        public readonly int $maxIterations = 8,
    ) {}
}
