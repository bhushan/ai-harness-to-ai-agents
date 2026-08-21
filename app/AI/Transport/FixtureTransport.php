<?php

namespace App\AI\Transport;

/**
 * Serves model responses from committed files, in call order within a scenario.
 *
 * Matching is by position, never by looking at the prompt, so a demo can never
 * quietly answer with the wrong fixture. Every outgoing request is written to
 * disk so a real payload can be opened on stage.
 */
final class FixtureTransport implements LlmTransport
{
    /** @var array<string, int> */
    private array $calls = [];

    /** @var array<string, true> */
    private array $recordingsCleared = [];

    public function __construct(
        private readonly string $fixturePath,
        private readonly ?string $recordPath = null,
    ) {}

    public function send(string $scenario, array $request): array
    {
        $call = $this->calls[$scenario] = ($this->calls[$scenario] ?? 0) + 1;

        $this->record($scenario, $call, $request);

        $path = $this->fixturePathFor($scenario, $call);

        if (! is_file($path)) {
            throw MissingFixtureException::for($scenario, $call, $path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw MissingFixtureException::invalidJson($path);
        }

        return $decoded;
    }

    public function callCount(string $scenario): int
    {
        return $this->calls[$scenario] ?? 0;
    }

    /**
     * Forget which fixtures have been served.
     *
     * Each command normally runs in its own process, where the count starts at
     * zero anyway. This exists for demo:verify, which runs several commands in
     * one process and needs each of them to behave as if it had just started.
     */
    public function rewind(): void
    {
        $this->calls = [];
        $this->recordingsCleared = [];
    }

    public function fixturePathFor(string $scenario, int $call): string
    {
        return $this->fixturePath.'/'.$scenario.'/'.self::index($call).'-response.json';
    }

    public function recordedRequestPathFor(string $scenario, int $call): ?string
    {
        if ($this->recordPath === null) {
            return null;
        }

        return $this->recordPath.'/'.$scenario.'/'.self::index($call).'-request.json';
    }

    private function record(string $scenario, int $call, array $request): void
    {
        if ($this->recordPath === null) {
            return;
        }

        $directory = $this->recordPath.'/'.$scenario;

        if (! isset($this->recordingsCleared[$scenario])) {
            $this->clear($directory);
            $this->recordingsCleared[$scenario] = true;
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $directory.'/'.self::index($call).'-request.json',
            json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function clear(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*.json') ?: [] as $file) {
            unlink($file);
        }
    }

    private static function index(int $call): string
    {
        return str_pad((string) $call, 2, '0', STR_PAD_LEFT);
    }
}
