<?php

namespace App\Console\Commands;

use App\AI\Agents\Agent;
use App\AI\Agents\AgentBrief;
use App\AI\Agents\AgentIteration;
use App\AI\Audit\AuditLog;
use App\Models\Customer;
use App\Support\DemoDatabase;
use App\Support\DemoPrinter;
use Illuminate\Console\Command;

/**
 * Step 5. A goal instead of a sequence.
 */
class DemoAgentCommand extends Command
{
    protected $signature = 'demo:agent
        {--goal= : What the agent is asked to achieve, if not the default for the scenario}
        {--scenario=double-charge : Which situation to run}
        {--max-iterations=8 : Hard cap on turns of the loop}';

    protected $description = 'Run the agent loop: reason, choose a tool, execute, observe, repeat';

    /**
     * Each situation is a different customer and a different fixture set. The
     * agent code is identical for both.
     *
     * @var array<string, array{scenario: string, customer: int}>
     */
    private const SITUATIONS = [
        'double-charge' => [
            'scenario' => 'agent-double-charge',
            'customer' => 1,
            'goal' => "Handle this customer's billing issue",
        ],
        'legitimate' => [
            'scenario' => 'agent-legitimate',
            'customer' => 2,
            'goal' => "Handle this customer's billing issue",
        ],
        'refund' => [
            'scenario' => 'agent-refund',
            'customer' => 3,
            'goal' => 'This customer has asked for their enterprise payment to be refunded. Sort it out.',
        ],
    ];

    public function handle(Agent $agent, AuditLog $audit): int
    {
        $printer = DemoPrinter::for($this->output);

        $situation = $this->option('scenario');

        if (! isset(self::SITUATIONS[$situation])) {
            $printer->blank();
            $printer->bad('There is no scenario named ['.$situation.'].');
            $printer->note('Known scenarios: '.implode(', ', array_keys(self::SITUATIONS)).'.');
            $printer->blank();

            return self::FAILURE;
        }

        // Reset first, so the ticket the agent opens is #1 on every run.
        DemoDatabase::reset();

        $audit->useContext('demo:agent --scenario='.$situation);

        $brief = new AgentBrief(
            goal: (string) ($this->option('goal') ?: self::SITUATIONS[$situation]['goal']),
            customer: Customer::findOrFail(self::SITUATIONS[$situation]['customer']),
            scenario: self::SITUATIONS[$situation]['scenario'],
            maxIterations: (int) $this->option('max-iterations'),
        );

        $printer->title('Step 5: the agent', 'A goal, a loop, and a hard cap');

        $printer->section('THE BRIEF', '', 'blue;options=bold');
        $printer->kv('goal', $brief->goal);
        $printer->kv('customer', $brief->customer->name.' (id '.$brief->customer->id.')');
        $printer->kv('acting as', $brief->actor->name);
        $printer->kv('permissions', implode(', ', $brief->actor->permissions));
        $printer->kv('iteration cap', (string) $brief->maxIterations);
        $printer->blank();
        $printer->note('There is no list of steps. Nobody wrote down which tool to call first.');

        $run = $agent->run($brief, function (AgentIteration $iteration, int $cap) use ($printer) {
            $printer->iteration($iteration->number, $cap);

            // On the closing turn the text is the answer itself, and it is
            // printed once below rather than twice here.
            if ($iteration->toolCalls !== []) {
                $printer->section('REASONING', 'stop_reason: '.$iteration->stopReason, 'white;options=bold');
                $printer->paragraph($iteration->reasoning === '' ? '(no text this turn)' : $iteration->reasoning);
            } else {
                $printer->section('REASONING', 'stop_reason: '.$iteration->stopReason, 'white;options=bold');
                $printer->note('No tool call this turn. The agent has what it needs and is answering.');
            }

            foreach ($iteration->toolCalls as $call) {
                $printer->toolCall($call->tool.'   ['.$call->impact->label().']', $call->input);

                if ($call->result->isError) {
                    $printer->section('RESULT', 'failed', 'red;options=bold');
                    $printer->bad($call->result->summary);

                    continue;
                }

                $printer->section('RESULT', $call->result->summary, 'yellow');
                $printer->json($call->result->toArray());
            }
        });

        if ($run->conclusion !== null) {
            $printer->answer($run->conclusion);
        }

        $printer->section('TRANSCRIPT');
        $printer->kv('iterations', count($run->iterations).' of '.$brief->maxIterations);
        $printer->kv('tools called', implode(' → ', $run->toolsCalled()) ?: 'none');
        $printer->kv('stopped because', $run->stoppedBecause);

        if ($run->ticketId !== null) {
            $printer->outcome('ticket #'.$run->ticketId.' opened', 'The agent decided a human had to act, and said what it wanted them to do.');
        } else {
            $printer->outcome('no ticket opened', 'The agent decided nothing here needed a human.');
        }

        $printer->section('WHAT TO NOTICE');
        $printer->bullet('Nobody wrote the order of those tool calls. The model chose each one');
        $printer->note('   after reading the result of the last.');
        $printer->blank();
        $printer->bullet('It did not stop at two payments and shout duplicate. It checked what');
        $printer->note('   the order was for first.');
        $printer->blank();
        $printer->bullet('Now run the same command against the other customer:');
        $printer->note('   php artisan demo:agent --scenario='.($situation === 'double-charge' ? 'legitimate' : 'double-charge'));
        $printer->note('   Same code, same tools, same goal. Different path, different answer.');
        $printer->blank();
        $printer->bullet('The cap is hard, and every decision is in the transcript. That is');
        $printer->note('   what makes a loop like this safe enough to run.');
        $printer->blank();
        $printer->bullet('Every tool call above is in the audit table:');
        $printer->note('   php artisan demo:audit');
        $printer->blank();

        return self::SUCCESS;
    }
}
