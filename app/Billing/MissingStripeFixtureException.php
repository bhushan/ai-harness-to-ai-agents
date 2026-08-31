<?php

namespace App\Billing;

use RuntimeException;

final class MissingStripeFixtureException extends RuntimeException
{
    public static function for(string $object, string $id, string $expectedPath): self
    {
        $relative = str_replace(base_path().'/', '', $expectedPath);

        return new self(implode("\n", [
            '',
            'No Stripe fixture for '.$object.' ['.$id.'].',
            '',
            'Expected this file to exist:',
            '    '.$relative,
            '',
            'This demo never calls Stripe. Add the fixture rather than expecting a',
            'live lookup to fill the gap.',
            '',
        ]));
    }
}
