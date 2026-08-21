<?php

namespace App\AI\Transport;

use RuntimeException;

final class MissingFixtureException extends RuntimeException
{
    public static function for(string $scenario, int $call, string $expectedPath): self
    {
        $relative = str_replace(base_path().'/', '', $expectedPath);

        return new self(implode("\n", [
            '',
            'No model fixture for scenario ['.$scenario.'], call #'.$call.'.',
            '',
            'Expected this file to exist:',
            '    '.$relative,
            '',
            'This demo never calls the network, so a missing fixture stops the run',
            'instead of inventing an answer. Either the scenario made more model',
            'calls than it has fixtures, or the fixture file is misnamed.',
            '',
        ]));
    }

    public static function invalidJson(string $path): self
    {
        $relative = str_replace(base_path().'/', '', $path);

        return new self("\nModel fixture is not valid JSON:\n    ".$relative."\n");
    }
}
