<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoHarnessCommandTest extends TestCase
{
    public function test_it_prints_the_assembled_prompt_the_context_and_the_answer(): void
    {
        $this->artisan('demo:harness')
            ->expectsOutputToContain('SYSTEM PROMPT')
            ->expectsOutputToContain('CONTEXT')
            ->expectsOutputToContain('Priya Sharma')
            ->assertExitCode(0);
    }

    public function test_the_answer_now_knows_who_it_is_talking_to(): void
    {
        $this->assertStringContainsString('Priya', $this->fixtureAnswer());
    }

    public function test_the_answer_admits_it_cannot_see_the_payment_records(): void
    {
        $answer = $this->fixtureAnswer();

        $this->assertStringContainsString('payment records', $answer);

        foreach (['999', '123', '124'] as $detail) {
            $this->assertStringNotContainsString(
                $detail,
                $answer,
                'Context is not data access. The model still cannot see '.$detail.'.'
            );
        }
    }

    private function fixtureAnswer(): string
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/fixtures/llm/harness/01-response.json')),
            true
        );

        return $fixture['content'][0]['text'];
    }
}
