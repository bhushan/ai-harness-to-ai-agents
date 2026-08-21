<?php

namespace Tests\Feature;

use App\AI\Workflows\DoubleChargeWorkflow;
use App\Models\SupportTicket;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoubleChargeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
    }

    public function test_it_runs_the_tools_in_the_order_the_developer_declared(): void
    {
        $run = app(DoubleChargeWorkflow::class)->run(customerId: 1, scenario: 'workflow');

        $this->assertSame(
            ['get_customer', 'get_payments', 'get_orders', 'create_ticket'],
            array_map(fn ($step) => $step->tool, $run->steps)
        );

        $this->assertSame([1, 2, 3, 4], array_map(fn ($step) => $step->number, $run->steps));
    }

    public function test_the_order_is_the_same_no_matter_which_customer_it_is_pointed_at(): void
    {
        $priya = app(DoubleChargeWorkflow::class)->run(customerId: 1, scenario: 'workflow');
        $arjun = app(DoubleChargeWorkflow::class)->run(customerId: 2, scenario: 'workflow-legitimate');

        $this->assertSame(
            array_map(fn ($step) => $step->tool, $priya->steps),
            array_map(fn ($step) => $step->tool, $arjun->steps),
            'A workflow cannot change its mind. That is the definition of a workflow.'
        );
    }

    public function test_it_opens_a_ticket_carrying_the_evidence_it_collected(): void
    {
        $run = app(DoubleChargeWorkflow::class)->run(customerId: 1, scenario: 'workflow');

        $ticket = SupportTicket::findOrFail($run->ticketId);

        $this->assertStringContainsString('ORD-2201', $ticket->subject);
        $this->assertStringContainsString('123', $ticket->body);
        $this->assertStringContainsString('124', $ticket->body);
        $this->assertSame('high', $ticket->priority);
    }

    public function test_it_opens_a_ticket_even_when_the_payments_are_legitimate(): void
    {
        $run = app(DoubleChargeWorkflow::class)->run(customerId: 2, scenario: 'workflow-legitimate');

        $this->assertNotNull(
            $run->ticketId,
            'The developer wrote create_ticket into the sequence, so it always runs. '
            .'That is the cost of a workflow, and the reason step 5 exists.'
        );
    }
}
