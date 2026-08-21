<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoToolsCommandTest extends TestCase
{
    public function test_it_shows_the_whole_round_trip(): void
    {
        $this->artisan('demo:tools')
            ->expectsOutputToContain('"tools"')
            ->expectsOutputToContain('tool_use')
            ->expectsOutputToContain('get_payments')
            ->expectsOutputToContain('tool_result')
            ->assertExitCode(0);
    }

    public function test_the_executed_tool_result_holds_real_database_rows(): void
    {
        $this->artisan('demo:tools')
            ->expectsOutputToContain('checkout_2026-08-19T10:15:01_9f21ab')
            ->expectsOutputToContain('checkout_2026-08-19T10:15:04_c7d038')
            ->assertExitCode(0);
    }

    public function test_the_final_answer_names_both_payments(): void
    {
        $answer = $this->fixtureAnswer();

        $this->assertStringContainsString('payment 123', $answer);
        $this->assertStringContainsString('payment 124', $answer);
        $this->assertStringContainsString('₹999', $answer);
        $this->assertStringContainsString('ORD-2201', $answer);
    }

    public function test_the_first_response_asks_rather_than_answers(): void
    {
        $fixture = $this->fixture('01');

        $this->assertSame('tool_use', $fixture['stop_reason']);
        $this->assertSame('get_payments', $fixture['content'][1]['name']);
        $this->assertSame(1, $fixture['content'][1]['input']['customer_id']);
    }

    private function fixtureAnswer(): string
    {
        return $this->fixture('02')['content'][0]['text'];
    }

    private function fixture(string $index): array
    {
        return json_decode(
            (string) file_get_contents(base_path("tests/fixtures/llm/tools/{$index}-response.json")),
            true
        );
    }
}
