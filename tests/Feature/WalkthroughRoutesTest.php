<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke tests for the browser walkthrough.
 *
 * They assert that each route can build everything it intends to dump. If a
 * method is renamed under one of these routes, this fails here rather than on
 * stage. See App\Support\DemoDump for why the routes do not dd() under test.
 */
class WalkthroughRoutesTest extends TestCase
{
    public function test_the_index_lists_the_steps_available_on_this_branch(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('/step-0')
            ->assertSee('php artisan demo:data');
    }

    public function test_step_6_shows_the_gate_holding(): void
    {
        $sections = $this->get('/step-6')->assertOk()->json('sections');

        $this->assertContains('1. every tool, classified', $sections);
        $this->assertContains('4. the approval now waiting for a human', $sections);
        $this->assertContains('5. the payment it asked about', $sections);
    }

    public function test_the_after_approval_view_does_not_reset_the_database(): void
    {
        $this->artisan('demo:agent', ['--scenario' => 'refund'])->assertExitCode(0);
        $this->artisan('demo:approve 1')->assertExitCode(0);

        $this->get('/step-6/after-approval')->assertOk();

        $this->assertNotNull(
            \App\Models\Payment::findOrFail(301)->refunded_at,
            'Opening the view must not undo the approval that was just released.'
        );
    }

    public function test_step_5_shows_the_transcript_and_the_conversation(): void
    {
        $sections = $this->get('/step-5')->assertOk()->json('sections');

        $this->assertContains('2. the transcript', $sections);
        $this->assertContains('4. the conversation it built', $sections);
    }

    public function test_step_4_shows_the_sequence_running_twice(): void
    {
        $sections = $this->get('/step-4')->assertOk()->json('sections');

        $this->assertContains('1. the sequence', $sections);
        $this->assertContains('5. pointed at Arjun, who needed nothing', $sections);
        $this->assertContains('6. and it opened him a ticket anyway', $sections);
    }

    public function test_step_3_shows_the_whole_round_trip(): void
    {
        $sections = $this->get('/step-3')->assertOk()->json('sections');

        $this->assertContains('1. the tools we offer', $sections);
        $this->assertContains('5. what our code actually ran and got', $sections);
        $this->assertContains('6. the tool_result we hand back', $sections);
    }

    public function test_step_2_shows_both_halves_of_the_harness(): void
    {
        $sections = $this->get('/step-2')->assertOk()->json('sections');

        $this->assertContains('1. the instructions half of the harness', $sections);
        $this->assertContains('5. what a tool would have had to return', $sections);
    }

    public function test_step_1_shows_the_request_and_the_answer(): void
    {
        $sections = $this->get('/step-1')->assertOk()->json('sections');

        $this->assertContains('1. the request, exactly as it goes to /v1/messages', $sections);
        $this->assertContains('3. the answer on its own', $sections);
    }

    public function test_step_0_shows_the_scenario_and_the_fake_transports(): void
    {
        $sections = $this->get('/step-0')->assertOk()->json('sections');

        $this->assertContains('3. what stands in for api.anthropic.com', $sections);
        $this->assertContains('5. the gateway record behind payment 124', $sections);
    }
}
