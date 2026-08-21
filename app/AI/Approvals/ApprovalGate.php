<?php

namespace App\AI\Approvals;

use App\AI\Tools\Actor;
use App\AI\Tools\Tool;
use App\AI\Tools\ToolResult;
use App\Models\Approval;

/**
 * The gate in front of high impact tools.
 *
 * The agent gets a real answer back, so the loop keeps working and it can
 * explain itself to the customer. What it does not get is the action.
 */
final class ApprovalGate
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function request(Tool $tool, array $input, Actor $actor): ToolResult
    {
        $approval = Approval::create([
            'tool' => $tool->name(),
            'input' => $input,
            'requested_by' => $actor->name,
            'reason' => $input['reason'] ?? null,
            'status' => 'pending',
        ]);

        return ToolResult::ok([
            'status' => 'pending_approval',
            'approval_id' => $approval->id,
            'tool' => $tool->name(),
            'requested' => $input,
            'message' => 'This action is high impact and was not carried out. It is waiting for a '
                .'human to approve it with: php artisan demo:approve '.$approval->id,
        ], 'approval #'.$approval->id.' requested, nothing executed');
    }
}
