<?php

use App\AI\AnthropicClient;
use App\AI\MessagesRequest;
use App\AI\Transport\LlmTransport;
use App\Billing\StripeGateway;
use App\Models\Customer;
use App\Models\Payment;
use App\Support\DemoDatabase;
use App\Support\DemoDump;
use App\AI\Harness\SupportHarness;
use App\AI\Tools\Tool;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolRegistry;
use App\Console\Commands\DemoHarnessCommand;
use App\AI\Agents\Agent;
use App\AI\Agents\AgentBrief;
use App\AI\Workflows\DoubleChargeWorkflow;
use App\Console\Commands\DemoToolsCommand;
use App\Models\Approval;
use App\Models\SupportTicket;
use App\Models\ToolCallLog;
use App\Console\Commands\DemoLlmCommand;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| The walkthrough
|------------------------------------------------------------------------------
|
| One route per teaching step, each one a dd() of the things worth looking at.
| The artisan commands tell the story in sequence; these routes open the hood,
| so a payload can be expanded and collapsed at the speed of the room.
|
| Run php artisan serve and start at http://localhost:8000.
|
*/

$steps = [
    [
        'url' => '/step-0',
        'title' => 'Setup',
        'blurb' => 'The scenario in SQLite, and the two fixture backed transports that replace the network.',
        'command' => 'php artisan demo:data',
    ],
    [
        'url' => '/step-1',
        'title' => 'The model on its own',
        'blurb' => 'A question and nothing else. Three fields go out, and a competent answer about nobody comes back.',
        'command' => 'php artisan demo:llm',
    ],
    [
        'url' => '/step-2',
        'title' => 'The harness',
        'blurb' => 'Instructions plus context. The answer improves without the model changing, and then admits what it cannot see.',
        'command' => 'php artisan demo:harness',
    ],
    [
        'url' => '/step-3',
        'title' => 'Tools',
        'blurb' => 'The model asks instead of answering. My code validates the arguments and runs real Eloquent.',
        'command' => 'php artisan demo:tools',
    ],
    [
        'url' => '/step-4',
        'title' => 'The workflow',
        'blurb' => 'A sequence I wrote. Same four steps for every customer, including the one who needed nothing.',
        'command' => 'php artisan demo:workflow',
    ],
    [
        'url' => '/step-5',
        'title' => 'The agent',
        'blurb' => 'A goal instead of a sequence. Expand the transcript: every decision it made is an object.',
        'command' => 'php artisan demo:agent',
    ],
    [
        'url' => '/step-6',
        'title' => 'Boundaries',
        'blurb' => 'The agent asks to move ₹50,000 and is stopped. Every tool now declares an impact and a permission.',
        'command' => 'php artisan demo:agent --scenario=refund',
    ],
    [
        'url' => '/step-6/after-approval',
        'title' => 'Boundaries: the human half',
        'blurb' => 'Run demo:approve 1 in the terminal, then open this. It does not reset, so you see what changed.',
        'command' => 'php artisan demo:approve 1',
    ],
];

Route::get('/step-0', function (LlmTransport $transport, StripeGateway $gateway) {
    // This route doubles as the reset button: it puts the database back to the
    // seeded scenario, so a demo can always be started again from here.
    DemoDatabase::reset();

    return DemoDump::these([
        '1. the customer at the centre of the talk' => Customer::findOrFail(1),

        '2. her payments: same order, three seconds apart' => Payment::with('order')
            ->where('customer_id', 1)
            ->orderBy('id')
            ->get(),

        // Expand this one. There is no base URL, no client, no key: just a
        // directory of files and a counter.
        '3. what stands in for api.anthropic.com' => $transport,

        '4. the scenarios it can serve' => array_map(
            'basename',
            glob(config('demo.fixtures.llm').'/*') ?: []
        ),

        // A real Stripe charge object, shape for shape, from a committed file.
        '5. the gateway record behind payment 124' => $gateway->retrieveCharge('ch_3PriyaSharmaAA0002'),
    ]);
});

Route::get('/step-1', function (AnthropicClient $client) {
    $response = $client->send(
        DemoLlmCommand::SCENARIO,
        MessagesRequest::make()->withUserMessage(DemoLlmCommand::QUESTION)
    );

    return DemoDump::these([
        // Three keys. This is the entire input the model gets.
        '1. the request, exactly as it goes to /v1/messages' => $response->request(),

        // A real Messages API response: content blocks, stop_reason, usage.
        '2. the raw response' => $response->raw(),

        '3. the answer on its own' => $response->text(),

        // Search this for Priya, or 999, or 123. They are not there, and they
        // cannot be: nothing in the request said they exist.
        '4. what the answer never mentions' => ['Priya Sharma', '₹999', 'payment 123', 'ORD-2201'],
    ]);
});

Route::get('/step-2', function (AnthropicClient $client) {
    DemoDatabase::ensure();

    $harness = new SupportHarness($client);
    $customer = Customer::findOrFail(1);

    $response = $harness->ask(DemoHarnessCommand::SCENARIO, DemoHarnessCommand::QUESTION, $customer);

    return DemoDump::these([
        '1. the instructions half of the harness' => $harness->systemPrompt(),

        // Who the customer is. Deliberately not what they paid.
        '2. the context half' => $harness->customerContext($customer),

        '3. the assembled request' => $response->request(),

        '4. the answer, which now knows her name' => $response->text(),

        // This is sitting in SQLite the whole time. The model cannot see it,
        // because nothing in the harness went and fetched it.
        '5. what a tool would have had to return' => Payment::with('order')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get(),
    ]);
});

Route::get('/step-3', function (AnthropicClient $client, ToolRegistry $registry, ToolExecutor $executor) {
    DemoDatabase::ensure();

    $harness = new SupportHarness($client);
    $customer = Customer::findOrFail(1);

    $request = $harness->buildRequest(DemoToolsCommand::QUESTION, $customer, $registry->schemas());
    $first = $client->send(DemoToolsCommand::SCENARIO, $request);

    $call = $first->toolUses()[0];
    $result = $executor->run($call['name'], $call['input']);

    $toolResult = [
        'type' => 'tool_result',
        'tool_use_id' => $call['id'],
        'content' => $result->toContent(),
    ];

    $second = $client->send(DemoToolsCommand::SCENARIO, $request
        ->withMessage('assistant', $first->contentBlocks())
        ->withMessage('user', [$toolResult]));

    return DemoDump::these([
        // Four ordinary Laravel classes, rendered as the API wants them.
        '1. the tools we offer' => $registry->schemas(),

        '2. the request, now carrying those tools' => $first->request(),

        // stop_reason is tool_use. It did not answer, it asked.
        '3. what came back' => $first->raw(),

        '4. the tool the model chose, and its arguments' => $call,

        // Not mocked. Eloquent against SQLite, plus a gateway lookup.
        '5. what our code actually ran and got' => $result->toArray(),

        '6. the tool_result we hand back' => $toolResult,

        '7. the final answer, every number of it from step 5' => $second->text(),
    ]);
});

Route::get('/step-4', function (DoubleChargeWorkflow $workflow) {
    // This route writes tickets, so it starts from the seeded state.
    DemoDatabase::reset();

    $priya = $workflow->run(customerId: 1, scenario: 'workflow');
    $arjun = $workflow->run(customerId: 2, scenario: 'workflow-legitimate');

    return DemoDump::these([
        // A constant. The model never sees it and cannot reorder it.
        '1. the sequence' => DoubleChargeWorkflow::SEQUENCE,

        '2. every step that ran, in order' => $priya->steps,

        '3. the ticket it opened' => SupportTicket::find($priya->ticketId),

        '4. the reply, written by the one model call at the end' => $priya->summary?->text(),

        // Same four tools, same order, for a customer with nothing wrong.
        '5. pointed at Arjun, who needed nothing' => array_map(
            fn ($step) => $step->tool,
            $arjun->steps
        ),

        '6. and it opened him a ticket anyway' => SupportTicket::find($arjun->ticketId),
    ]);
});

Route::get('/step-5', function (Agent $agent) {
    DemoDatabase::reset();

    $brief = new AgentBrief(
        goal: "Handle this customer's billing issue",
        customer: Customer::findOrFail(1),
        scenario: 'agent-double-charge',
    );

    $run = $agent->run($brief);

    return DemoDump::these([
        // No list of steps anywhere in here.
        '1. the brief' => $brief,

        // Expand this. Iterations, each with its reasoning and its tool calls,
        // each tool call carrying the result it got back.
        '2. the transcript' => $run,

        '3. the tools it chose, in the order it chose them' => $run->toolsCalled(),

        // The loop, seen from the wire: user, assistant with a tool_use,
        // user with a tool_result, and round again.
        '4. the conversation it built' => $run->conversation,

        '5. the ticket that came out of it' => SupportTicket::find($run->ticketId),
    ]);
});

Route::get('/step-6', function (Agent $agent, ToolRegistry $registry) {
    DemoDatabase::reset();

    $brief = new AgentBrief(
        goal: 'This customer has asked for their enterprise payment to be refunded. Sort it out.',
        customer: Customer::findOrFail(3),
        scenario: 'agent-refund',
    );

    $run = $agent->run($brief);

    return DemoDump::these([
        // The whole boundary model, in one table.
        '1. every tool, classified' => collect($registry->all())
            ->map(fn (Tool $tool) => [
                'impact' => $tool->impact()->value,
                'permission to call' => $tool->permission(),
                'permission to approve' => $tool->approvalPermission(),
            ]),

        '2. who the agent is acting as' => $brief->actor,

        // Expand the last iteration: it asked for refund_payment and got back
        // pending_approval. It never got the action.
        '3. the transcript' => $run,

        '4. the approval now waiting for a human' => Approval::find(1),

        // Still succeeded. Still not refunded. Nothing moved.
        '5. the payment it asked about' => Payment::find(301),

        '6. the audit log' => ToolCallLog::orderBy('id')->get(),

        '7. next' => 'Run php artisan demo:approve 1, then open /step-6/after-approval.',
    ]);
});

// Deliberately does not reset, so it shows the state the approval left behind.
Route::get('/step-6/after-approval', function () {
    return DemoDump::these([
        '1. the approval, and who released it' => Approval::find(1),

        '2. the payment, refunded' => Payment::find(301),

        // Two runs, two actors. The agent asked; a person executed.
        '3. the audit log, both halves' => ToolCallLog::orderBy('id')->get(),
    ]);
});

Route::get('/', function () use ($steps) {
    $rows = collect($steps)->map(fn (array $step) => <<<HTML
        <li>
            <a href="{$step['url']}">{$step['url']}</a>
            <h2>{$step['title']}</h2>
            <p>{$step['blurb']}</p>
            <code>{$step['command']}</code>
        </li>
        HTML)->implode('');

    return <<<HTML
        <!doctype html>
        <meta charset="utf-8">
        <title>From AI harness to AI agents</title>
        <style>
            :root { color-scheme: dark }
            body { background: #16161a; color: #e8e8ea; font: 18px/1.6 ui-sans-serif, system-ui, sans-serif;
                   margin: 0; padding: 4rem 2rem; display: flex; justify-content: center }
            main { width: min(56rem, 100%) }
            h1 { font-size: 2.4rem; margin: 0 0 .4rem }
            .lede { color: #9a9aa2; margin: 0 0 3rem; font-size: 1.1rem }
            ol { list-style: none; margin: 0; padding: 0 }
            li { border-top: 1px solid #2a2a31; padding: 1.6rem 0 }
            a { color: #7dd3fc; font-size: 1.6rem; text-decoration: none; font-family: ui-monospace, monospace }
            a:hover { text-decoration: underline }
            h2 { font-size: 1.15rem; margin: .4rem 0 .2rem; font-weight: 600 }
            p { margin: 0 0 .7rem; color: #b9b9c2 }
            code { color: #86efac; font-family: ui-monospace, monospace; font-size: .95rem }
        </style>
        <main>
            <h1>From AI harness to AI agents</h1>
            <p class="lede">One route per step. Everything below runs offline, from committed fixtures.</p>
            <ol>{$rows}</ol>
        </main>
        HTML;
});
