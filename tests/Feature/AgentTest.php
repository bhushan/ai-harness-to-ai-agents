<?php

namespace Tests\Feature;

use App\AI\Agents\Agent;
use App\AI\Agents\AgentBrief;
use App\Models\Customer;
use App\Models\SupportTicket;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
    }

    public function test_the_double_charge_run_investigates_before_it_acts(): void
    {
        $run = app(Agent::class)->run($this->brief(1, 'agent-double-charge'));

        $this->assertSame(
            ['get_customer', 'get_payments', 'get_orders', 'create_ticket'],
            $run->toolsCalled()
        );

        $this->assertSame('the agent stopped calling tools', $run->stoppedBecause);
    }

    public function test_the_double_charge_run_ends_with_a_ticket_and_a_conclusion(): void
    {
        $run = app(Agent::class)->run($this->brief(1, 'agent-double-charge'));

        $this->assertNotNull($run->ticketId);
        $this->assertStringContainsString('124', $run->conclusion);
        $this->assertSame(1, SupportTicket::count());
    }

    public function test_the_legitimate_run_reaches_the_opposite_decision(): void
    {
        $run = app(Agent::class)->run($this->brief(2, 'agent-legitimate'));

        $this->assertSame(['get_customer', 'get_payments', 'get_orders'], $run->toolsCalled());
        $this->assertNull($run->ticketId);
        $this->assertSame(0, SupportTicket::count(), 'Nothing needed a human, so nothing was escalated.');
    }

    public function test_both_runs_had_the_same_tools_available(): void
    {
        $double = app(Agent::class)->run($this->brief(1, 'agent-double-charge'));

        $this->assertSame(
            ['get_customer', 'get_orders', 'get_payments', 'create_ticket'],
            array_column($double->iterations[0]->toolsOffered, 'name')
        );
    }

    public function test_it_records_every_iteration_in_a_transcript(): void
    {
        $run = app(Agent::class)->run($this->brief(1, 'agent-double-charge'));

        $this->assertCount(5, $run->iterations);
        $this->assertSame([1, 2, 3, 4, 5], array_map(fn ($i) => $i->number, $run->iterations));
        $this->assertNotSame('', $run->iterations[0]->reasoning);
    }

    public function test_the_iteration_cap_is_hard(): void
    {
        $run = app(Agent::class)->run($this->brief(1, 'agent-double-charge', maxIterations: 2));

        $this->assertCount(2, $run->iterations);
        $this->assertSame('the iteration cap of 2 was reached', $run->stoppedBecause);
        $this->assertNull($run->conclusion);
    }

    private function brief(int $customerId, string $scenario, int $maxIterations = 8): AgentBrief
    {
        return new AgentBrief(
            goal: "Handle this customer's billing issue",
            customer: Customer::findOrFail($customerId),
            scenario: $scenario,
            maxIterations: $maxIterations,
        );
    }
}
