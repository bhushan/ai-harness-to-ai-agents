<?php

namespace App\AI\Tools;

use RuntimeException;

final class UnknownToolException extends RuntimeException
{
    /**
     * @param  array<int, string>  $known
     */
    public static function named(string $name, array $known): self
    {
        return new self(
            'The model asked for a tool named ['.$name.'], which is not registered. '
            .'Registered tools: '.implode(', ', $known).'.'
        );
    }
}
