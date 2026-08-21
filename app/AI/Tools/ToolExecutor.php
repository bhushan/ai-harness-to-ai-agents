<?php

namespace App\AI\Tools;

use App\AI\Approvals\ApprovalGate;
use App\AI\Audit\AuditLog;
use App\Models\Approval;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs a tool the model asked for, or declines to.
 *
 * Four checks, in this order, and every outcome is written to the audit log:
 *
 *   1. is the tool registered
 *   2. is this actor allowed to call it
 *   3. is the input actually valid
 *   4. is this something an agent may do, or only request
 *
 * The model is an untrusted caller. Boundaries belong here, in one place,
 * rather than scattered through the tools or trusted to the system prompt.
 */
final class ToolExecutor
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ApprovalGate $gate,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function run(string $name, array $input, ?Actor $actor = null): ToolResult
    {
        $actor ??= Actor::assistant();

        if (! $this->registry->has($name)) {
            return ToolResult::failed('There is no tool named ['.$name.'].');
        }

        $tool = $this->registry->get($name);
        $started = microtime(true);

        if (! $actor->can($tool->permission())) {
            return $this->finish($tool, 'denied', $input, ToolResult::failed(
                'The actor ['.$actor->name.'] is not allowed to call ['.$name.'].'
            ), $actor, $started);
        }

        try {
            $validated = Validator::make($input, $tool->rules())->validate();
        } catch (ValidationException $exception) {
            return $this->finish($tool, 'invalid', $input, ToolResult::failed(
                implode(' ', $exception->validator->errors()->all())
            ), $actor, $started);
        }

        if ($tool->impact()->requiresApproval()) {
            return $this->finish(
                $tool,
                'pending_approval',
                $validated,
                $this->gate->request($tool, $validated, $actor),
                $actor,
                $started
            );
        }

        return $this->finish($tool, null, $validated, $this->attempt($tool, $validated), $actor, $started);
    }

    /**
     * Carry out a request that a human approved.
     *
     * This is the only path that runs a high impact tool, and it is reached
     * from a person typing a command, never from the loop.
     */
    public function executeApproved(Approval $approval, Actor $actor): ToolResult
    {
        $tool = $this->registry->get($approval->tool);
        $started = microtime(true);

        $permission = $tool->approvalPermission() ?? $tool->permission();

        if (! $actor->can($permission)) {
            return $this->finish($tool, 'denied', $approval->input, ToolResult::failed(
                'The actor ['.$actor->name.'] is not allowed to approve ['.$tool->name().'].'
            ), $actor, $started);
        }

        return $this->finish($tool, null, $approval->input, $this->attempt($tool, $approval->input), $actor, $started);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function attempt(Tool $tool, array $input): ToolResult
    {
        try {
            return $tool->execute($input);
        } catch (Throwable $exception) {
            // A tool that blows up is a bad tool_result, not a dead conversation.
            return ToolResult::failed($exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function finish(
        Tool $tool,
        ?string $decision,
        array $input,
        ToolResult $result,
        Actor $actor,
        float $started,
    ): ToolResult {
        $this->audit->record(
            tool: $tool->name(),
            impact: $tool->impact(),
            decision: $decision ?? ($result->isError ? 'failed' : 'executed'),
            input: $input,
            result: $result,
            actor: $actor,
            durationMs: (microtime(true) - $started) * 1000,
        );

        return $result;
    }
}
