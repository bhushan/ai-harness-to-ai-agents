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
