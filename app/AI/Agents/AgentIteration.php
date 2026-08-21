<?php

namespace App\AI\Agents;

/**
 * One turn of the loop, kept so the whole run can be read back afterwards.
 *
 * An agent that cannot be replayed cannot be trusted, so the transcript is a
 * first class object rather than something scraped out of the log file.
 */
final class AgentIteration
{
    /**
     * @param  array<int, AgentToolCall>  $toolCalls
     * @param  array<int, array<string, mixed>>  $toolsOffered
     */
    public function __construct(
        public readonly int $number,
        public readonly string $reasoning,
        public readonly string $stopReason,
        public readonly array $toolCalls,
        public readonly array $toolsOffered,
    ) {}
}
