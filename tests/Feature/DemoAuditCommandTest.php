<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoAuditCommandTest extends TestCase
{
    public function test_it_prints_every_tool_call_from_the_last_run(): void
    {
        $this->artisan('demo:agent')->assertExitCode(0);

        $this->artisan('demo:audit')
            ->expectsOutputToContain('get_customer')
            ->expectsOutputToContain('get_payments')
            ->expectsOutputToContain('get_orders')
            ->expectsOutputToContain('create_ticket')
            ->expectsOutputToContain('executed')
            ->assertExitCode(0);
    }

    public function test_it_shows_the_gate_holding_in_the_refund_run(): void
    {
        $this->artisan('demo:agent', ['--scenario' => 'refund'])->assertExitCode(0);

        $this->artisan('demo:audit')
            ->expectsOutputToContain('high_impact')
            ->expectsOutputToContain('refund_payment')
            ->expectsOutputToContain('pending_approval')
            ->assertExitCode(0);
    }

    public function test_it_says_so_when_there_is_nothing_to_show(): void
    {
        $this->artisan('demo:data')->assertExitCode(0);

        $this->artisan('demo:audit')
            ->expectsOutputToContain('No tool calls')
            ->assertExitCode(0);
    }
}
