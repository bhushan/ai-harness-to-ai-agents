<?php

namespace App\Console\Commands;

use App\AI\Workflows\DoubleChargeWorkflow;
use App\AI\Workflows\WorkflowStep;
use App\Support\DemoDatabase;
use App\Support\DemoPrinter;
use Illuminate\Console\Command;

/**
 * Step 4. The same four tools, driven by a sequence I wrote.
 */
class DemoWorkflowCommand extends Command
{
    protected $signature = 'demo:workflow {--customer=1 : Which customer to run the workflow against}';

    protected $description = 'Run a hardcoded pipeline over the tools: check customer, payments, orders, open a ticket';

    public function handle(DoubleChargeWorkflow $workflow): int
    {
        $printer = DemoPrinter::for($this->output);

        // Reset first so the ticket id is the same on every run.
        DemoDatabase::reset();

        $printer->title('Step 4: the workflow', 'A sequence I wrote, running over the same four tools');

        $printer->section('THE SEQUENCE', 'App\AI\Workflows\DoubleChargeWorkflow::SEQUENCE', 'blue;options=bold');
        foreach (DoubleChargeWorkflow::SEQUENCE as $index => $definition) {
            $printer->kv(($index + 1).'.  '.$definition['label'], $definition['tool'], 26);
        }
        $printer->blank();
        $printer->note('This list is a constant in my code. The model never sees it, and');
        $printer->note('cannot add to it, skip an entry, or change the order.');

        $customerId = (int) $this->option('customer');

        // One fixture set per customer, because the closing model call says
        // something different about each of them.
        $scenario = $customerId === 1 ? 'workflow' : 'workflow-legitimate';

        $run = $workflow->run($customerId, $scenario, function (WorkflowStep $step, int $total) use ($printer) {
            $printer->step($step->number, $total, $step->label);
            $printer->blank();
            $printer->toolCall($step->tool, $step->input);

            if ($step->result->isError) {
                $printer->bad($step->result->summary);

                return;
            }

            $printer->section('--- TOOL RESULT', $step->result->summary, 'yellow');
            $printer->json($step->result->toArray());
        });

        if ($run->summary !== null) {
            $printer->answer($run->summary->text());
        }

        $printer->section('WHAT TO NOTICE');
        $printer->bullet('Four steps, one model call, and the model call is last. It writes');
        $printer->note('   the reply. It does not decide what to look up.');
        $printer->blank();
        $printer->bullet('The sequence is a constant in my code. Predictable, auditable, cheap.');
        $printer->blank();
        $printer->bullet('Run it against a customer whose payments are perfectly normal:');
        $printer->note('   php artisan demo:workflow --customer=2');
        $printer->note('   It still opens a high priority ticket, because I told it to. A');
        $printer->note('   workflow cannot notice that it did not need to.');
        $printer->blank();

        if ($run->ticketId !== null) {
            $printer->outcome('ticket #'.$run->ticketId.' opened', 'Opened by the workflow, whether or not it was needed.');
        }

        return self::SUCCESS;
    }
}
