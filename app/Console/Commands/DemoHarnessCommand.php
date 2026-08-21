<?php

namespace App\Console\Commands;

use App\AI\AnthropicClient;
use App\AI\Harness\SupportHarness;
use App\Models\Customer;
use App\Support\DemoDatabase;
use App\Support\DemoPrinter;
use Illuminate\Console\Command;

/**
 * Step 2. Same model, same question, with instructions and context around it.
 */
class DemoHarnessCommand extends Command
{
    protected $signature = 'demo:harness';

    protected $description = 'Ask the same question through a harness: instructions plus context, still no tools';

    public const SCENARIO = 'harness';

    public const QUESTION = 'Why was I charged twice?';

    public function handle(AnthropicClient $client): int
    {
        $printer = DemoPrinter::for($this->output);

        DemoDatabase::ensure();

        $printer->title('Step 2: the harness', 'Instructions plus context, still no tools');

        $harness = new SupportHarness($client);
        $customer = Customer::findOrFail(1);

        $printer->section('SYSTEM PROMPT', 'the instructions half of the harness', 'blue;options=bold');
        $printer->paragraph($harness->systemPrompt());

        $printer->section('CONTEXT', 'the customer record, injected into the user turn', 'blue;options=bold');
        foreach (explode("\n", $harness->customerContext($customer)) as $line) {
            $printer->note($line);
        }

        $response = $harness->ask(self::SCENARIO, self::QUESTION, $customer);

        $printer->request($response->request());
        $printer->answer($response->text());
        $printer->section('<<< RESPONSE METADATA', '', 'green;options=bold');
        $printer->kv('stop_reason', $response->stopReason());
        $printer->kv('input tokens', (string) $response->inputTokens());

        $printer->section('WHAT TO NOTICE');
        $printer->bullet('The answer improved without the model improving. Same model, same');
        $printer->note('   question. Only the harness around it changed.');
        $printer->blank();
        $printer->bullet('It now knows Priya by name and can repeat what it was told.');
        $printer->blank();
        $printer->bullet('It still cannot answer the question. It says so, and it says what it');
        $printer->note('   would need: the payment records. Context is not data access.');
        $printer->blank();
        $printer->bullet('Somebody has to fetch those records. Right now that somebody is me,');
        $printer->note('   by hand, before every call. That does not scale past one customer.');
        $printer->blank();

        return self::SUCCESS;
    }
}
