<?php

namespace App\Console\Commands;

use App\AI\Audit\AuditLog;
use App\AI\Transport\FixtureTransport;
use App\AI\Transport\LlmTransport;
use App\Support\DemoPrinter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * The pre-flight check. Run this once before walking on stage.
 *
 * Every demo command available on this branch is executed for real, against the
 * committed fixtures, and its output is checked for the lines the talk depends
 * on. Commands that belong to later branches are reported as skipped rather
 * than treated as failures.
 */
class DemoVerifyCommand extends Command
{
    protected $signature = 'demo:verify';

    protected $description = 'Run every demo command on this branch and assert its output';

    /**
     * @return array<int, array{command: string, arguments: array<string, mixed>, expect: array<int, string>}>
     */
    protected function checks(): array
    {
        return [
            [
                'command' => 'demo:data',
                'arguments' => [],
                'expect' => ['Priya Sharma', 'ORD-2201', '₹999', 'Arjun Mehta'],
            ],
            [
                'command' => 'demo:llm',
                'arguments' => [],
                'expect' => ['>>> REQUEST', 'Why was I charged twice?', 'authorisation hold'],
            ],
            [
                'command' => 'demo:harness',
                'arguments' => [],
                'expect' => ['SYSTEM PROMPT', 'CONTEXT', 'Priya Sharma', 'payment records'],
            ],
            [
                'command' => 'demo:tools',
                'arguments' => [],
                'expect' => ['"tools"', 'tool_use', 'get_payments', 'tool_result', 'payment 123', 'payment 124'],
            ],
            [
                'command' => 'demo:workflow',
                'arguments' => [],
                'expect' => ['STEP 1 OF 4', 'STEP 4 OF 4', 'payment 123', 'ticket #1'],
            ],
            [
                'command' => 'demo:workflow',
                'arguments' => ['--customer' => 2],
                'expect' => ['Arjun', 'neither of them is a duplicate', 'ticket #1'],
            ],
            [
                'command' => 'demo:agent',
                'arguments' => [],
                'expect' => ['ITERATION 1', 'ITERATION 5', 'REASONING', 'TOOL CALL', 'RESULT', 'ticket #1', 'payment 124'],
            ],
            [
                'command' => 'demo:agent',
                'arguments' => ['--scenario' => 'legitimate'],
                'expect' => ['ITERATION 4', 'no refund is owed', 'NO TICKET OPENED'],
            ],
            [
                'command' => 'demo:agent',
                'arguments' => ['--max-iterations' => 2],
                'expect' => ['the iteration cap of 2 was reached'],
            ],
            // These three run in order on purpose: the agent requests the
            // refund, the human releases it, the audit shows both halves.
            [
                'command' => 'demo:agent',
                'arguments' => ['--scenario' => 'refund'],
                'expect' => ['HIGH IMPACT', 'pending_approval', 'demo:approve 1'],
            ],
            [
                'command' => 'demo:approve',
                'arguments' => ['id' => 1],
                'expect' => ['₹50,000', 're_3VikramNairCC0001', 'REFUND RELEASED'],
            ],
            [
                'command' => 'demo:audit',
                'arguments' => [],
                'expect' => ['RUNS RECORDED', 'demo:approve', 'refund_payment'],
            ],
        ];
    }

    public function handle(): int
    {
        $printer = DemoPrinter::for($this->output);
        $printer->title('Demo verify', 'Every command on this branch, run for real against the fixtures');

        $failures = 0;
        $skipped = 0;

        foreach ($this->checks() as $check) {
            $label = trim($check['command'].' '.$this->argumentsToLabel($check['arguments']));

            if (! $this->getApplication()?->has($check['command'])) {
                $printer->note('–  '.$label.'   not on this branch');
                $skipped++;

                continue;
            }

            $problem = $this->runCheck($check);

            if ($problem === null) {
                $printer->good($label);

                continue;
            }

            $printer->bad($label);
            $printer->note('   '.$problem);
            $failures++;
        }

        $printer->blank();

        if ($failures > 0) {
            $printer->bad($failures.' check(s) failed. Do not go on stage yet.');
            $printer->blank();

            return self::FAILURE;
        }

        $printer->outcome(
            'ready',
            $skipped > 0
                ? 'Every command on this branch behaved as expected. '.$skipped.' command(s) belong to later branches and were skipped.'
                : 'Every command on this branch behaved as expected.'
        );

        return self::SUCCESS;
    }

    /**
     * @param  array{command: string, arguments: array<string, mixed>, expect: array<int, string>}  $check
     */
    private function runCheck(array $check): ?string
    {
        // Each command would normally run in its own process, with its fixture
        // counter starting at zero. Verify runs them back to back, so give each
        // one the same clean start it would get from the shell.
        $transport = app(LlmTransport::class);

        if ($transport instanceof FixtureTransport) {
            $transport->rewind();
        }

        app(AuditLog::class)->newRun();

        // The buffer is passed in explicitly. Some demo commands reset the
        // database with a nested Artisan call, which would otherwise replace the
        // buffer we are trying to read.
        $buffer = new BufferedOutput;

        try {
            $exitCode = Artisan::call($check['command'], $check['arguments'], $buffer);
        } catch (Throwable $exception) {
            return 'threw '.$exception::class.': '.trim(explode("\n", $exception->getMessage())[1] ?? $exception->getMessage());
        }

        if ($exitCode !== self::SUCCESS) {
            return 'exited with code '.$exitCode;
        }

        $output = $buffer->fetch();

        foreach ($check['expect'] as $expected) {
            if (! str_contains($output, $expected)) {
                return 'output did not contain "'.$expected.'"';
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function argumentsToLabel(array $arguments): string
    {
        return collect($arguments)
            ->map(fn ($value, $key) => is_bool($value) ? $key : $key.'='.$value)
            ->implode(' ');
    }
}
