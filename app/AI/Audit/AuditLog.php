<?php

namespace App\AI\Audit;

use App\AI\Tools\Actor;
use App\AI\Tools\ToolImpact;
use App\AI\Tools\ToolResult;
use App\Models\ToolCallLog;

/**
 * Every tool call, whether it ran, was refused, or was parked for approval.
 *
 * A log line in a file is fine for engineers. A table is what you need when
 * somebody asks, six weeks later, what the agent did on a Tuesday and on whose
 * authority.
 */
final class AuditLog
{
    private ?int $runId = null;

    private string $context = 'cli';

    /**
     * One run is one command invocation. The id is allocated on the first call
     * of the process, after any reset has happened.
     */
    public function runId(): int
    {
        return $this->runId ??= ((int) ToolCallLog::max('run_id')) + 1;
    }

    /**
     * Forget the current run id.
     *
     * Each command normally runs in its own process and gets a fresh run for
     * free. This exists for demo:verify, which runs several commands back to
     * back and needs each of them recorded as its own run.
     */
    public function newRun(): void
    {
        $this->runId = null;
    }

    public function useContext(string $context): void
    {
        $this->context = $context;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function record(
        string $tool,
        ToolImpact $impact,
        string $decision,
        array $input,
        ToolResult $result,
        Actor $actor,
        float $durationMs,
    ): ToolCallLog {
        return ToolCallLog::create([
            'run_id' => $this->runId(),
            'context' => $this->context,
            'actor' => $actor->name,
            'tool' => $tool,
            'impact' => $impact->value,
            'decision' => $decision,
            'input' => $input,
            'output' => $result->toArray(),
            'duration_ms' => (int) round($durationMs),
        ]);
    }
}
