<?php

namespace App\AI\Agents;

final class AgentRun
{
    /**
     * @param  array<int, AgentIteration>  $iterations
     */
    public function __construct(
        public readonly array $iterations,
        public readonly ?string $conclusion,
        public readonly string $stoppedBecause,
        public readonly ?int $ticketId,
    ) {}

    /**
     * Every tool the agent chose to call, in the order it chose to call them.
     *
     * @return array<int, string>
     */
    public function toolsCalled(): array
    {
        $tools = [];

        foreach ($this->iterations as $iteration) {
            foreach ($iteration->toolCalls as $call) {
                $tools[] = $call->tool;
            }
        }

        return $tools;
    }
}
