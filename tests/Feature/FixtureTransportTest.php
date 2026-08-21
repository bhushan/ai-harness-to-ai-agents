<?php

namespace Tests\Feature;

use App\AI\Transport\FixtureTransport;
use App\AI\Transport\MissingFixtureException;
use Tests\TestCase;

class FixtureTransportTest extends TestCase
{
    private string $fixtures;

    private string $records;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = sys_get_temp_dir().'/demo-fixtures-'.getmypid();
        $this->records = sys_get_temp_dir().'/demo-records-'.getmypid();

        $this->writeFixture('sample', 1, ['id' => 'msg_one', 'stop_reason' => 'end_turn']);
        $this->writeFixture('sample', 2, ['id' => 'msg_two', 'stop_reason' => 'end_turn']);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->fixtures);
        $this->deleteDirectory($this->records);

        parent::tearDown();
    }

    public function test_it_returns_fixtures_in_call_order_within_a_scenario(): void
    {
        $transport = new FixtureTransport($this->fixtures, $this->records);

        $this->assertSame('msg_one', $transport->send('sample', ['messages' => []])['id']);
        $this->assertSame('msg_two', $transport->send('sample', ['messages' => []])['id']);
    }

    public function test_it_counts_calls_per_scenario_not_globally(): void
    {
        $this->writeFixture('other', 1, ['id' => 'msg_other']);

        $transport = new FixtureTransport($this->fixtures, $this->records);
        $transport->send('sample', []);

        $this->assertSame('msg_other', $transport->send('other', [])['id']);
    }

    public function test_it_records_every_outgoing_request(): void
    {
        $transport = new FixtureTransport($this->fixtures, $this->records);
        $transport->send('sample', ['model' => 'claude-opus-5', 'max_tokens' => 1024]);

        $recorded = json_decode(
            (string) file_get_contents($this->records.'/sample/01-request.json'),
            true
        );

        $this->assertSame('claude-opus-5', $recorded['model']);
    }

    public function test_it_clears_previous_recordings_for_the_scenario(): void
    {
        @mkdir($this->records.'/sample', 0777, true);
        file_put_contents($this->records.'/sample/99-request.json', '{"stale":true}');

        (new FixtureTransport($this->fixtures, $this->records))->send('sample', []);

        $this->assertFileDoesNotExist($this->records.'/sample/99-request.json');
    }

    public function test_it_fails_loudly_when_a_scenario_runs_out_of_fixtures(): void
    {
        $transport = new FixtureTransport($this->fixtures, $this->records);
        $transport->send('sample', []);
        $transport->send('sample', []);

        $this->expectException(MissingFixtureException::class);
        $this->expectExceptionMessageMatches('/scenario \[sample\]/');
        $this->expectExceptionMessageMatches('/call #3/');
        $this->expectExceptionMessageMatches('/03-response\.json/');

        $transport->send('sample', []);
    }

    private function writeFixture(string $scenario, int $index, array $body): void
    {
        $directory = $this->fixtures.'/'.$scenario;
        @mkdir($directory, 0777, true);

        file_put_contents(
            $directory.'/'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'-response.json',
            json_encode($body, JSON_PRETTY_PRINT)
        );
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path.'/'.$entry;
            is_dir($full) ? $this->deleteDirectory($full) : @unlink($full);
        }

        @rmdir($path);
    }
}
