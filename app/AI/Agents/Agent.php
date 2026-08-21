<?php

namespace App\AI\Agents;

use App\AI\AnthropicClient;
use App\AI\MessagesRequest;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolImpact;
use App\AI\Tools\ToolRegistry;
use Closure;

/**
 * The loop.
 *
 * Reason, choose a tool, execute it, observe the result, repeat until the model
 * stops asking for tools or the iteration cap is hit. That is the entire
 * difference between step 4 and step 5: the order of the tool calls is decided
 * one turn at a time, by the model, instead of once, in advance, by me.
 *
 * Two things keep that from being reckless. The cap is hard, and every decision
 * is recorded.
 */
final class Agent
{
    public function __construct(
        private readonly AnthropicClient $client,
        private readonly ToolRegistry $registry,
        private readonly ToolExecutor $executor,
    ) {}

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are a billing support agent working inside a company's own systems, for customers in India. Amounts are in Indian rupees.

        You are given a goal, not a script. Decide for yourself what to look up, in what order, and when you have enough to answer. Use the tools rather than asking the customer to fetch things for you.

        Every amount, date, id and reference in your conclusion must have come back from a tool. Never invent one.

        Two payments are not automatically a mistake. Check whether they belong to the same order and were taken seconds apart, or whether they are separate purchases made at different times for different things.

        Some tools are marked high impact. Calling one records a request for a human to approve; it does not carry the action out. If you request one, say plainly in your reply that it is pending approval rather than done.

        Open a ticket only when a human genuinely has to act, and say plainly what you want them to do. If nothing needs a human, do not open a ticket.

        When you have the answer, stop calling tools and write the reply.
        PROMPT;
    }

    public function run(AgentBrief $brief, ?Closure $onIteration = null): AgentRun
    {
        $tools = $this->registry->schemas();

        $request = MessagesRequest::make()
            ->withSystem($this->systemPrompt())
            ->withTools($tools)
            ->withMessage('user', [
                ['type' => 'text', 'text' => $this->context($brief)],
                ['type' => 'text', 'text' => 'Goal: '.$brief->goal],
            ]);

        $iterations = [];
        $conclusion = null;
        $ticketId = null;
        $stoppedBecause = 'the iteration cap of '.$brief->maxIterations.' was reached';

        for ($number = 1; $number <= $brief->maxIterations; $number++) {
            $response = $this->client->send($brief->scenario, $request);

            $calls = [];
            $resultBlocks = [];

            foreach ($response->toolUses() as $use) {
                $result = $this->executor->run($use['name'], $use['input'], $brief->actor);

                $calls[] = new AgentToolCall(
                    id: $use['id'],
                    tool: $use['name'],
                    impact: $this->registry->has($use['name'])
                        ? $this->registry->get($use['name'])->impact()
                        : ToolImpact::Read,
                    input: $use['input'],
                    result: $result,
                );

                if ($use['name'] === 'create_ticket' && ! $result->isError) {
                    $ticketId = $result->data['ticket_id'];
                }

                $resultBlocks[] = array_filter([
                    'type' => 'tool_result',
                    'tool_use_id' => $use['id'],
                    'content' => $result->toContent(),
                    'is_error' => $result->isError ?: null,
                ], fn ($value) => $value !== null);
            }

            $iteration = new AgentIteration(
                number: $number,
                reasoning: $response->text(),
                stopReason: $response->stopReason(),
                toolCalls: $calls,
                toolsOffered: $tools,
            );

            $iterations[] = $iteration;

            if ($onIteration !== null) {
                $onIteration($iteration, $brief->maxIterations);
            }

            if (! $response->wantsToolUse()) {
                $conclusion = $response->text();
                $stoppedBecause = 'the agent stopped calling tools';

                break;
            }

            // Feed the loop: the assistant turn that asked, then the results.
            $request = $request
                ->withMessage('assistant', $response->contentBlocks())
                ->withMessage('user', $resultBlocks);
        }

        return new AgentRun(
            iterations: $iterations,
            conclusion: $conclusion,
            stoppedBecause: $stoppedBecause,
            ticketId: $ticketId,
            // Worth keeping: this is how the conversation grew, one turn at a
            // time, and it is the clearest picture of what a loop actually is.
            conversation: $request->messages(),
        );
    }

    private function context(AgentBrief $brief): string
    {
        $customer = $brief->customer;

        return <<<CONTEXT
        <customer_record>
        id: {$customer->id}
        name: {$customer->name}
        email: {$customer->email}
        plan: {$customer->plan}
        </customer_record>
        CONTEXT;
    }
}
