<?php

namespace App\AI\Harness;

use App\AI\AnthropicClient;
use App\AI\MessagesRequest;
use App\AI\MessagesResponse;
use App\Models\Customer;

/**
 * The harness: everything that surrounds the model call.
 *
 * A model call is one HTTP request. A harness is the code that decides what
 * goes into it: which instructions, which context, in which order, under which
 * limits. This one assembles a system prompt and the customer record. It has
 * no tools, so the model can be told who it is talking to and nothing more.
 */
final class SupportHarness
{
    public function __construct(private readonly ?AnthropicClient $client = null) {}

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are a billing support assistant for a subscription product used by customers in India.

        Answer the customer directly, in plain language, and address them by name. Amounts are in Indian rupees.

        Never invent an amount, a date, a payment id or an order reference. If the information you need to answer is not in the context you were given, say so plainly, say exactly what you would need to see, and stop there.

        Do not promise a refund. A human decides refunds.
        PROMPT;
    }

    /**
     * The customer record, rendered for the model.
     *
     * Note what is here and what is not. The harness knows who the customer is.
     * It does not know what they paid, because nothing in this step can read
     * the payments table on the model's behalf.
     */
    public function customerContext(Customer $customer): string
    {
        return <<<CONTEXT
        <customer_record>
        name: {$customer->name}
        email: {$customer->email}
        phone: {$customer->phone}
        plan: {$customer->plan}
        customer since: {$customer->joined_at->format('d M Y')}
        </customer_record>
        CONTEXT;
    }

    public function buildRequest(string $question, Customer $customer): MessagesRequest
    {
        return MessagesRequest::make()
            ->withSystem($this->systemPrompt())
            ->withMessage('user', [
                ['type' => 'text', 'text' => $this->customerContext($customer)],
                ['type' => 'text', 'text' => $question],
            ]);
    }

    public function ask(string $scenario, string $question, Customer $customer): MessagesResponse
    {
        if ($this->client === null) {
            throw new \LogicException('This harness was built without a client, so it can only assemble requests.');
        }

        return $this->client->send($scenario, $this->buildRequest($question, $customer));
    }
}
