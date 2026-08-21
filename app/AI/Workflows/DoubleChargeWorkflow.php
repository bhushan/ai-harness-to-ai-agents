<?php

namespace App\AI\Workflows;

use App\AI\AnthropicClient;
use App\AI\MessagesRequest;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolResult;
use App\Support\Money;
use Closure;

/**
 * A workflow: the same four steps, in the same order, every single time.
 *
 * The model is used once, at the end, to turn what was found into something a
 * customer can read. It is not asked which tool to call, or in what order, or
 * whether a ticket is warranted. All of that is decided here, in PHP, by me.
 *
 * That is the strength of a workflow and also its ceiling. It is predictable,
 * auditable and cheap. It is also incapable of noticing that a customer with
 * two perfectly ordinary payments did not need a ticket at all.
 */
final class DoubleChargeWorkflow
{
    /**
     * The sequence. Read it top to bottom; there is nothing else to know.
     *
     * @var array<int, array{label: string, tool: string}>
     */
    public const SEQUENCE = [
        ['label' => 'Check the customer', 'tool' => 'get_customer'],
        ['label' => 'Check the payments', 'tool' => 'get_payments'],
        ['label' => 'Check the orders', 'tool' => 'get_orders'],
        ['label' => 'Open a ticket', 'tool' => 'create_ticket'],
    ];

    public function __construct(
        private readonly ToolExecutor $executor,
        private readonly AnthropicClient $client,
    ) {}

    /**
     * @param  string  $scenario  Which fixture set the closing model call reads.
     */
    public function run(int $customerId, string $scenario, ?Closure $onStep = null): WorkflowRun
    {
        $steps = [];
        $results = [];
        $total = count(self::SEQUENCE);

        foreach (self::SEQUENCE as $index => $definition) {
            $number = $index + 1;

            $input = $this->inputFor($definition['tool'], $customerId, $results);
            $result = $this->executor->run($definition['tool'], $input);

            $results[$definition['tool']] = $result;

            $step = new WorkflowStep(
                number: $number,
                label: $definition['label'],
                tool: $definition['tool'],
                input: $input,
                result: $result,
            );

            $steps[] = $step;

            if ($onStep !== null) {
                $onStep($step, $total);
            }
        }

        return new WorkflowRun(
            steps: $steps,
            ticketId: $results['create_ticket']->data['ticket_id'] ?? null,
            summary: $this->summarise($results, $scenario),
        );
    }

    /**
     * Each step's input is derived here, in code, from what earlier steps
     * returned. The model has no say in it.
     *
     * @param  array<string, ToolResult>  $results
     * @return array<string, mixed>
     */
    private function inputFor(string $tool, int $customerId, array $results): array
    {
        if ($tool !== 'create_ticket') {
            return ['customer_id' => $customerId];
        }

        $payments = $results['get_payments']->data['payments'] ?? [];
        $reference = $payments[0]['order_reference'] ?? 'unknown order';

        $lines = array_map(
            fn (array $payment) => sprintf(
                '  payment %d, %s, %s, settled %s',
                $payment['id'],
                $payment['amount'],
                $payment['status'],
                $payment['paid_at'],
            ),
            $payments
        );

        $total = array_sum(array_column($payments, 'amount_paise'));

        return [
            'customer_id' => $customerId,
            'subject' => 'Possible duplicate charge on '.$reference,
            'body' => implode("\n", [
                'Opened automatically by the double charge workflow.',
                '',
                count($payments).' payment(s) on this account, '.Money::inr($total).' in total:',
                ...$lines,
                '',
                'Please review and decide whether a refund is owed.',
            ]),
            'priority' => 'high',
        ];
    }

    /**
     * The one model call in the whole workflow, and it is the last thing that
     * happens: turn the collected facts into a reply a customer can read.
     *
     * @param  array<string, ToolResult>  $results
     */
    private function summarise(array $results, string $scenario): \App\AI\MessagesResponse
    {
        $findings = collect($results)
            ->map(fn (ToolResult $result, string $tool) => $tool.': '.$result->toContent())
            ->implode("\n\n");

        $request = MessagesRequest::make()
            ->withSystem(
                'You are a billing support assistant. You are given the output of a fixed sequence of '
                .'lookups that has already run. Write the reply to the customer. Use only what is in '
                .'the findings. Amounts are in Indian rupees.'
            )
            ->withMessage('user', [
                ['type' => 'text', 'text' => "<findings>\n".$findings."\n</findings>"],
                ['type' => 'text', 'text' => 'Write the reply to the customer.'],
            ]);

        return $this->client->send($scenario, $request);
    }
}
