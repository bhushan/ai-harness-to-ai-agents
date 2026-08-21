<?php

namespace App\Console\Commands;

use App\AI\AnthropicClient;
use App\AI\Harness\SupportHarness;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolRegistry;
use App\Models\Customer;
use App\Support\DemoDatabase;
use App\Support\DemoPrinter;
use Illuminate\Console\Command;

/**
 * Step 3. One tool round trip, end to end.
 *
 * Everything except the two model responses is real: the tools array is
 * generated from the registry, the tool call is executed against SQLite, and
 * the tool_result sent back holds whatever the database actually returned.
 */
class DemoToolsCommand extends Command
{
    protected $signature = 'demo:tools';

    protected $description = 'Give the model tools and watch one round trip: tool_use, execution, tool_result, answer';

    public const SCENARIO = 'tools';

    public const QUESTION = 'Why was I charged twice?';

    public function handle(
        AnthropicClient $client,
        ToolRegistry $registry,
        ToolExecutor $executor,
    ): int {
        $printer = DemoPrinter::for($this->output);

        DemoDatabase::ensure();

        $printer->title('Step 3: tools', 'One round trip: tool_use, real execution, tool_result, answer');

        $harness = new SupportHarness($client);
        $customer = Customer::findOrFail(1);

        $printer->section('TOOLS ON OFFER', 'from App\AI\Tools\ToolRegistry', 'blue;options=bold');
        foreach ($registry->all() as $tool) {
            $printer->kv($tool->name(), class_basename($tool), 20);
        }

        $request = $harness->buildRequest(self::QUESTION, $customer, $registry->schemas());
        $first = $client->send(self::SCENARIO, $request);

        $printer->request($first->request());

        $printer->section('<<< RESPONSE', $first->summary(), 'green;options=bold');
        $printer->json($first->raw());

        $call = $first->toolUses()[0] ?? null;

        if ($call === null) {
            $printer->bad('The model did not ask for a tool. Check the fixture.');

            return self::FAILURE;
        }

        $printer->toolCall($call['name'], $call['input']);

        $printer->section('--- REAL EXECUTION', 'this part is not mocked', 'yellow;options=bold');
        $printer->note('Running '.$registry->get($call['name'])::class);
        $printer->note('against the SQLite database, through App\AI\Tools\ToolExecutor.');
        $printer->blank();

        $result = $executor->run($call['name'], $call['input']);

        $printer->json($result->toArray());

        $toolResultBlock = array_filter([
            'type' => 'tool_result',
            'tool_use_id' => $call['id'],
            'content' => $result->toContent(),
            'is_error' => $result->isError ?: null,
        ], fn ($value) => $value !== null);

        $printer->section('--- TOOL RESULT SENT BACK', 'the next user turn', 'yellow;options=bold');
        $printer->json(['role' => 'user', 'content' => [$toolResultBlock]]);

        $second = $client->send(self::SCENARIO, $request
            ->withMessage('assistant', $first->contentBlocks())
            ->withMessage('user', [$toolResultBlock]));

        $printer->answer($second->text());

        $printer->section('WHAT TO NOTICE');
        $printer->bullet('The model did not answer. It asked. stop_reason came back as');
        $printer->note('   tool_use, with the tool name and arguments it wanted.');
        $printer->blank();
        $printer->bullet('My code decided whether to run it, validated the arguments, and');
        $printer->note('   executed ordinary Eloquent against SQLite. The model never touched');
        $printer->note('   the database.');
        $printer->blank();
        $printer->bullet('The answer now names payments 123 and 124, ₹999, ORD-2201 and the');
        $printer->note('   two idempotency keys. Every one of those came from the tool result.');
        $printer->blank();
        $printer->bullet('I still chose the order of events: one call, one execution, one');
        $printer->note('   answer. That choice is step 4.');
        $printer->blank();

        return self::SUCCESS;
    }
}
