<?php

namespace Tests\Feature;

use App\AI\Harness\SupportHarness;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SupportHarnessTest extends TestCase
{
    public function test_the_system_prompt_states_the_job_and_the_limits(): void
    {
        $prompt = (new SupportHarness)->systemPrompt();

        $this->assertStringContainsString('billing support', $prompt);
        $this->assertStringContainsString('Never invent', $prompt);
    }

    public function test_the_context_block_carries_the_customer_record(): void
    {
        $context = (new SupportHarness)->customerContext($this->priya());

        $this->assertStringContainsString('Priya Sharma', $context);
        $this->assertStringContainsString('priya.sharma@example.in', $context);
        $this->assertStringContainsString('Pro', $context);
    }

    public function test_the_context_block_carries_no_payment_records(): void
    {
        $context = (new SupportHarness)->customerContext($this->priya());

        foreach (['999', '123', '124', 'ORD-2201'] as $detail) {
            $this->assertStringNotContainsString(
                $detail,
                $context,
                'The harness injects who the customer is, not what they paid. That gap is step 2.'
            );
        }
    }

    public function test_the_request_carries_a_system_prompt_and_two_content_blocks(): void
    {
        $payload = (new SupportHarness)
            ->buildRequest('Why was I charged twice?', $this->priya())
            ->toArray('claude-opus-5', 1024);

        $this->assertArrayHasKey('system', $payload);
        $this->assertArrayNotHasKey('tools', $payload);

        $content = $payload['messages'][0]['content'];

        $this->assertCount(2, $content);
        $this->assertStringContainsString('Priya Sharma', $content[0]['text']);
        $this->assertSame('Why was I charged twice?', $content[1]['text']);
    }

    private function priya(): Customer
    {
        return new Customer([
            'name' => 'Priya Sharma',
            'email' => 'priya.sharma@example.in',
            'phone' => '+91 98200 11223',
            'plan' => 'Pro',
            'joined_at' => Carbon::parse('2026-05-14 09:12:00'),
        ]);
    }
}
