<?php

namespace App\Console\Commands;

use App\Models\ToolCallLog;
use App\Support\DemoPrinter;
use Illuminate\Console\Command;

/**
 * Step 6. What actually happened, according to the database.
 */
class DemoAuditCommand extends Command
{
    protected $signature = 'demo:audit {--run= : A specific run id, defaults to the most recent}';

    protected $description = 'Print every tool call from the last run';

    public function handle(): int
    {
        $printer = DemoPrinter::for($this->output);

        $printer->title('Step 6: the audit log', 'Every tool call, and what was decided about it');

        $runId = (int) ($this->option('run') ?: ToolCallLog::max('run_id'));

        if ($runId === 0) {
            $printer->note('No tool calls recorded yet. Run demo:agent or demo:workflow first.');
            $printer->blank();

            return self::SUCCESS;
        }

        $runs = ToolCallLog::orderBy('run_id')->get()->groupBy('run_id');

        if ($runs->count() > 1) {
            $printer->section('RUNS RECORDED', 'each one a separate command');
            $printer->table(
                ['RUN', 'COMMAND', 'CALLS', 'DECISIONS'],
                $runs->map(fn ($calls, $id) => [
                    $id,
                    $calls->first()->context,
                    $calls->count(),
                    $calls->countBy('decision')->map(fn ($count, $decision) => $decision.' x'.$count)->implode(', '),
                ])->values()->all()
            );
        }

        $calls = ToolCallLog::where('run_id', $runId)->orderBy('id')->get();

        if ($calls->isEmpty()) {
            $printer->note('No tool calls recorded for run '.$runId.'.');
            $printer->blank();

            return self::SUCCESS;
        }

        $printer->section('RUN '.$runId, $calls->first()->context.'   '.$calls->count().' tool call(s)');

        $printer->table(
            ['#', 'TOOL', 'IMPACT', 'DECISION', 'ACTOR', 'MS'],
            $calls->values()->map(fn (ToolCallLog $call, int $index) => [
                $index + 1,
                $call->tool,
                $call->impact,
                $call->decision,
                $call->actor,
                $call->duration_ms,
            ])->all()
        );

        $printer->section('WHAT WENT IN AND WHAT CAME BACK');
        foreach ($calls as $index => $call) {
            $printer->note(($index + 1).'.  '.$call->tool.'  ->  '.$call->decision);
            $printer->json(['input' => $call->input, 'output' => $call->output]);
            $printer->blank();
        }

        $printer->section('SUMMARY');
        foreach ($calls->countBy('decision') as $decision => $count) {
            $printer->kv($decision, (string) $count);
        }

        $printer->blank();
        $printer->note('Nothing here came from the model. These rows are written by the');
        $printer->note('executor, on every call, whatever the outcome.');
        $printer->blank();

        return self::SUCCESS;
    }
}
