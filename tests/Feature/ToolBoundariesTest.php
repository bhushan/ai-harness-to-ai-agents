<?php

namespace Tests\Feature;

use App\AI\Tools\Actor;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolImpact;
use App\AI\Tools\ToolRegistry;
use App\Models\Payment;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolBoundariesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
    }

    public function test_every_tool_declares_what_kind_of_thing_it_is(): void
    {
        $impacts = [];

        foreach (app(ToolRegistry::class)->all() as $tool) {
            $impacts[$tool->name()] = $tool->impact();
        }

        $this->assertSame([
            'get_customer' => ToolImpact::Read,
            'get_orders' => ToolImpact::Read,
            'get_payments' => ToolImpact::Read,
            'create_ticket' => ToolImpact::Write,
            'refund_payment' => ToolImpact::HighImpact,
        ], $impacts);
    }

    public function test_a_high_impact_tool_asks_instead_of_acting(): void
    {
        $result = app(ToolExecutor::class)->run('refund_payment', [
            'payment_id' => 301,
            'amount_paise' => 5000000,
            'reason' => 'requested_by_customer',
        ], Actor::assistant());

        $this->assertFalse($result->isError);
        $this->assertSame('pending_approval', $result->data['status']);
        $this->assertSame(1, $result->data['approval_id']);

        $this->assertNull(
            Payment::findOrFail(301)->refunded_at,
            'The gate is not advice. Nothing was refunded.'
        );
        $this->assertDatabaseCount('approvals', 1);
    }

    public function test_a_tool_the_actor_is_not_allowed_to_touch_is_refused(): void
    {
        $result = app(ToolExecutor::class)->run('refund_payment', [
            'payment_id' => 301,
            'amount_paise' => 5000000,
            'reason' => 'requested_by_customer',
        ], Actor::readOnly());

        $this->assertTrue($result->isError);
        $this->assertStringContainsString('not allowed', $result->summary);
        $this->assertDatabaseCount('approvals', 0);
    }

    public function test_read_tools_still_just_run(): void
    {
        $result = app(ToolExecutor::class)->run('get_customer', ['customer_id' => 1], Actor::assistant());

        $this->assertFalse($result->isError);
        $this->assertSame('Priya Sharma', $result->data['name']);
    }

    public function test_every_call_is_written_to_the_audit_log(): void
    {
        $executor = app(ToolExecutor::class);

        $executor->run('get_customer', ['customer_id' => 1], Actor::assistant());
        $executor->run('refund_payment', ['payment_id' => 301, 'amount_paise' => 5000000, 'reason' => 'duplicate'], Actor::assistant());
        $executor->run('refund_payment', ['payment_id' => 301, 'amount_paise' => 5000000, 'reason' => 'duplicate'], Actor::readOnly());

        $this->assertDatabaseCount('tool_call_logs', 3);
        $this->assertSame(
            ['executed', 'pending_approval', 'denied'],
            \App\Models\ToolCallLog::orderBy('id')->pluck('decision')->all()
        );
    }
}
