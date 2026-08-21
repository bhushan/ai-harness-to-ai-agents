<?php

namespace App\Console\Commands;

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
