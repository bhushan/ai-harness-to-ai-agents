<?php

namespace App\Console\Commands;

use App\AI\Audit\AuditLog;
use App\AI\Tools\Actor;
use App\AI\Tools\ToolExecutor;
use App\Models\Approval;
use App\Support\DemoPrinter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Step 6. The other side of the gate.
 *
 * This is the only path in the application that carries out a high impact
 * action, and it starts with a person typing a command.
 */
class DemoApproveCommand extends Command
{
    protected $signature = 'demo:approve {id : The approval to release}';

    protected $description = 'Approve a pending high impact request and actually carry it out';

    public function handle(ToolExecutor $executor, AuditLog $audit): int
    {
        $printer = DemoPrinter::for($this->output);

        $audit->useContext('demo:approve');

        $printer->title('Step 6: approval', 'A human releases what the agent could only ask for');

        $approval = Approval::find($this->argument('id'));

        if ($approval === null) {
            $printer->bad('No approval with id '.$this->argument('id').'.');
            $printer->note('Run php artisan demo:agent --scenario=refund first.');
            $printer->blank();

            return self::FAILURE;
        }

        if (! $approval->isPending()) {
            $printer->bad('Approval #'.$approval->id.' has already been '.$approval->status.'.');
            $printer->note('An approval is good for exactly one action.');
            $printer->blank();

            return self::FAILURE;
        }

        $printer->section('THE REQUEST', 'recorded by the agent, untouched since', 'blue;options=bold');
        $printer->kv('approval', '#'.$approval->id);
        $printer->kv('tool', $approval->tool);
        $printer->kv('requested by', $approval->requested_by);
        $printer->kv('reason', (string) $approval->reason);
        $printer->blank();
        $printer->json($approval->input);

        $approver = Actor::billingLead();

        $printer->section('APPROVING AS', $approver->name, 'blue;options=bold');
        $printer->note('The only actor in this application holding payments.refund.execute.');

        $result = $executor->executeApproved($approval, $approver);

        if ($result->isError) {
            $printer->section('RESULT', 'failed', 'red;options=bold');
            $printer->bad($result->summary);
            $printer->blank();

            return self::FAILURE;
        }

        $approval->update([
            'status' => 'approved',
            'result' => $result->toArray(),
            'approved_by' => $approver->name,
            'approved_at' => Carbon::now(),
        ]);

        $printer->section('--- REAL EXECUTION', 'the same tool class the agent asked for', 'yellow;options=bold');
        $printer->json($result->toArray());

        $printer->outcome(
            'refund released',
            $result->summary.'. The agent wrote the request. A person released it. The tool that ran is the '
            .'same class either way, which is the point: the boundary is in the harness, '
            .'not in the tool and not in the prompt.'
        );

        $printer->section('WHAT TO NOTICE');
        $printer->bullet('The agent could describe this action perfectly and still not do it.');
        $printer->blank();
        $printer->bullet('The approval carried the payment id, the amount and the reason, so');
        $printer->note('   nobody had to reconstruct the request by hand.');
        $printer->blank();
        $printer->bullet('Both halves are in the audit table, under different actors:');
        $printer->note('   php artisan demo:audit');
        $printer->blank();

        return self::SUCCESS;
    }
}
