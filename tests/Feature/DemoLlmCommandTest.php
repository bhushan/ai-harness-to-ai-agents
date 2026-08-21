<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoLlmCommandTest extends TestCase
{
    public function test_it_prints_the_outgoing_request_and_the_answer(): void
    {
        $this->artisan('demo:llm')
            ->expectsOutputToContain('>>> REQUEST')
            ->expectsOutputToContain('Why was I charged twice?')
            ->expectsOutputToContain('claude-opus-5')
            ->assertExitCode(0);
    }

    public function test_the_answer_knows_nothing_about_this_customer(): void
    {
        $answer = $this->fixtureAnswer();

        foreach (['Priya', '999', '123', '124', 'ORD-2201'] as $detail) {
            $this->assertStringNotContainsString(
                $detail,
                $answer,
                'A model with no context cannot know '.$detail.'. That is the point of this step.'
            );
        }
    }

    public function test_the_answer_is_the_generic_kind_of_help_a_support_page_gives(): void
    {
        $this->assertStringContainsString('authorisation', $this->fixtureAnswer());
    }

    private function fixtureAnswer(): string
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/fixtures/llm/llm-raw/01-response.json')),
            true
        );

        return $fixture['content'][0]['text'];
    }
}
