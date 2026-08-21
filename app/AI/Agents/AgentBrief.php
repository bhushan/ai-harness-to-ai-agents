<?php

namespace App\AI\Agents;

use App\AI\Tools\Actor;
use App\Models\Customer;

/**
 * What the agent is given: a goal, who it concerns, and the limits it runs under.
 *
 * Note what is missing. There is no list of steps.
 */
final class AgentBrief
{
    /**
     * Who the agent is acting as. It never holds more permission than the
     * person it stands in for.
     */
    public readonly Actor $actor;

    public function __construct(
        public readonly string $goal,
        public readonly Customer $customer,
        public readonly string $scenario,
        public readonly int $maxIterations = 8,
        ?Actor $actor = null,
    ) {
        $this->actor = $actor ?? Actor::assistant();
    }
}
