<?php

namespace Tests\Feature;

use App\AI\Tools\Actor;
use App\AI\Tools\ToolExecutor;
use App\Models\Approval;
use App\Models\Payment;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);

        app(ToolExecutor::class)->run('refund_payment', [
            'payment_id' => 301,
            'amount_paise' => 5000000,
            'reason' => 'requested_by_customer',
        ], Actor::assistant());
    }

    public function test_approving_is_what_actually_moves_the_money(): void
    {
        $this->artisan('demo:approve 1')
            ->expectsOutputToContain('₹50,000')
            ->expectsOutputToContain('re_3VikramNairCC0001')
            ->assertExitCode(0);

        $payment = Payment::findOrFail(301);

        $this->assertNotNull($payment->refunded_at);
        $this->assertSame(5000000, $payment->refunded_amount_paise);
        $this->assertSame('refunded', $payment->status);
        $this->assertSame('approved', Approval::findOrFail(1)->status);
    }

    public function test_an_approval_cannot_be_used_twice(): void
    {
        $this->artisan('demo:approve 1')->assertExitCode(0);

        $this->artisan('demo:approve 1')
            ->expectsOutputToContain('already')
            ->assertExitCode(1);
    }

    public function test_approving_something_that_does_not_exist_fails_cleanly(): void
    {
        $this->artisan('demo:approve 99')
            ->expectsOutputToContain('No approval')
            ->assertExitCode(1);
    }

    public function test_the_approval_carries_everything_needed_to_replay_it(): void
    {
        $approval = Approval::findOrFail(1);

        $this->assertSame('refund_payment', $approval->tool);
        $this->assertSame(301, $approval->input['payment_id']);
        $this->assertSame(5000000, $approval->input['amount_paise']);
        $this->assertSame('assistant', $approval->requested_by);
        $this->assertSame('pending', $approval->status);
    }
}
