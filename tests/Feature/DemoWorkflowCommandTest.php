<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use Tests\TestCase;

class DemoWorkflowCommandTest extends TestCase
{
    public function test_it_prints_a_step_counter_for_every_step(): void
    {
        $this->artisan('demo:workflow')
            ->expectsOutputToContain('STEP 1 OF 4')
            ->expectsOutputToContain('STEP 2 OF 4')
            ->expectsOutputToContain('STEP 3 OF 4')
            ->expectsOutputToContain('STEP 4 OF 4')
            ->assertExitCode(0);
    }

    public function test_it_shows_where_the_sequence_is_written(): void
    {
        $this->artisan('demo:workflow')
            ->expectsOutputToContain('DoubleChargeWorkflow')
            ->expectsOutputToContain('get_customer')
            ->expectsOutputToContain('create_ticket')
            ->assertExitCode(0);
    }

    public function test_it_resets_first_so_the_ticket_id_is_the_same_on_every_run(): void
    {
        // Whatever a previous run left behind, the command starts from the
        // seeded state, so the ticket it opens is always #1.
        $this->artisan('demo:data')->assertExitCode(0);
        SupportTicket::create([
            'customer_id' => 1,
            'subject' => 'Left over from an earlier run',
            'body' => 'This should not survive.',
            'priority' => 'low',
            'status' => 'open',
            'opened_by' => 'human',
        ]);

        $this->artisan('demo:workflow')
            ->expectsOutputToContain('ticket #1')
            ->assertExitCode(0);

        $this->assertSame(1, SupportTicket::count());
    }
}
