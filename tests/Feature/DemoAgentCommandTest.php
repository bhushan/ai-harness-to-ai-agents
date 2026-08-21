<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use Tests\TestCase;

class DemoAgentCommandTest extends TestCase
{
    public function test_it_prints_an_iteration_block_for_every_decision(): void
    {
        $this->artisan('demo:agent')
            ->expectsOutputToContain('ITERATION 1')
            ->expectsOutputToContain('REASONING')
            ->expectsOutputToContain('TOOL CALL')
            ->expectsOutputToContain('RESULT')
            ->expectsOutputToContain('ITERATION 5')
            ->assertExitCode(0);
    }

    public function test_the_double_charge_run_ends_with_the_ticket_it_opened(): void
    {
        $this->artisan('demo:agent')
            ->expectsOutputToContain('create_ticket')
            ->expectsOutputToContain('ticket #1')
            ->assertExitCode(0);

        $this->assertSame(1, SupportTicket::count());
    }

    public function test_the_legitimate_scenario_creates_no_ticket(): void
    {
        $this->artisan('demo:agent', ['--scenario' => 'legitimate'])
            ->expectsOutputToContain('get_payments')
            ->expectsOutputToContain('NO TICKET OPENED')
            ->assertExitCode(0);

        $this->assertSame(0, SupportTicket::count());
    }

    public function test_an_unknown_scenario_is_refused_with_the_list_of_known_ones(): void
    {
        $this->artisan('demo:agent', ['--scenario' => 'nonsense'])
            ->expectsOutputToContain('double-charge')
            ->assertExitCode(1);
    }
}
