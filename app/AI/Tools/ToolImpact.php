<?php

namespace App\AI\Tools;

/**
 * What a tool can do to the world.
 *
 * This is the first question to ask about any tool you hand to a model, and it
 * decides how the tool is treated: read tools run, write tools run and are
 * recorded, high impact tools are only ever requested.
 */
enum ToolImpact: string
{
    case Read = 'read';
    case Write = 'write';
    case HighImpact = 'high_impact';

    public function requiresApproval(): bool
    {
        return $this === self::HighImpact;
    }

    public function label(): string
    {
        return match ($this) {
            self::Read => 'read',
            self::Write => 'write',
            self::HighImpact => 'HIGH IMPACT',
        };
    }
}
