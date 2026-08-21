<?php

namespace Tests\Feature;

use App\AI\Tools\ToolExecutor;
use App\Models\SupportTicket;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
    }

    public function test_get_customer_reads_the_real_row(): void
    {
        $result = $this->runTool('get_customer', ['customer_id' => 1]);

        $this->assertFalse($result->isError);
        $this->assertSame('Priya Sharma', $result->data['name']);
        $this->assertSame('Pro', $result->data['plan']);
    }

    public function test_get_payments_returns_both_halves_of_the_double_charge(): void
    {
        $result = $this->runTool('get_payments', ['customer_id' => 1]);

        $this->assertCount(2, $result->data['payments']);
        $this->assertSame([123, 124], array_column($result->data['payments'], 'id'));
        $this->assertSame('₹999', $result->data['payments'][0]['amount']);
    }

    public function test_get_payments_carries_the_gateway_evidence(): void
    {
        $payments = $this->runTool('get_payments', ['customer_id' => 1])->data['payments'];

        $this->assertNotSame(
            $payments[0]['gateway']['idempotency_key'],
            $payments[1]['gateway']['idempotency_key'],
            'Different idempotency keys are what make this two submits rather than one retry.'
        );
    }

    public function test_get_orders_returns_the_order_both_payments_point_at(): void
    {
        $result = $this->runTool('get_orders', ['customer_id' => 1]);

        $this->assertCount(1, $result->data['orders']);
        $this->assertSame('ORD-2201', $result->data['orders'][0]['reference']);
    }

    public function test_create_ticket_writes_a_real_row(): void
    {
        $result = $this->runTool('create_ticket', [
            'customer_id' => 1,
            'subject' => 'Duplicate charge on ORD-2201',
            'body' => 'Payments 123 and 124 are both ₹999 against the same order.',
            'priority' => 'high',
        ]);

        $this->assertFalse($result->isError);
        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertSame(
            'Duplicate charge on ORD-2201',
            SupportTicket::findOrFail($result->data['ticket_id'])->subject
        );
    }

    public function test_a_tool_validates_its_own_input(): void
    {
        $result = $this->runTool('get_payments', ['customer_id' => 'not-a-number']);

        $this->assertTrue($result->isError);
        $this->assertStringContainsString('customer id', strtolower($result->summary));
    }

    public function test_a_tool_refuses_a_priority_it_does_not_recognise(): void
    {
        $result = $this->runTool('create_ticket', [
            'customer_id' => 1,
            'subject' => 'Test',
            'body' => 'Test',
            'priority' => 'apocalyptic',
        ]);

        $this->assertTrue($result->isError);
        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_a_tool_pointed_at_a_customer_who_does_not_exist_fails_cleanly(): void
    {
        $result = $this->runTool('get_customer', ['customer_id' => 9999]);

        $this->assertTrue($result->isError);
    }

    private function runTool(string $tool, array $input): \App\AI\Tools\ToolResult
    {
        return app(ToolExecutor::class)->run($tool, $input);
    }
}
