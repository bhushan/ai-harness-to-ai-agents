<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Payment;
use Tests\TestCase;

class DemoAgentRefundScenarioTest extends TestCase
{
    public function test_the_agent_is_stopped_at_the_gate(): void
    {
        // Each expectation is matched against a single line of output, and the
        // first one registered claims the line, so order these from the most
        // specific line outwards.
        $this->artisan('demo:agent', ['--scenario' => 'refund'])
            ->expectsOutputToContain('HIGH IMPACT')
            ->expectsOutputToContain('refund_payment')
            ->expectsOutputToContain('pending_approval')
            ->assertExitCode(0);

        $this->assertNull(
            Payment::findOrFail(301)->refunded_at,
            '₹50,000 did not move because an agent asked for it to move.'
        );
    }

    public function test_the_request_survives_as_something_a_human_can_act_on(): void
    {
        $this->artisan('demo:agent', ['--scenario' => 'refund'])->assertExitCode(0);

        $approval = Approval::findOrFail(1);

        $this->assertSame('refund_payment', $approval->tool);
        $this->assertSame('pending', $approval->status);
        $this->assertSame(5000000, $approval->input['amount_paise']);
    }
}
