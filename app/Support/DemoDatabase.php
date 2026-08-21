<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;

/**
 * Determinism helper.
 *
 * Commands that write to the database (tickets, refunds) reset to the seeded
 * state first, so the same command produces the same output on the tenth run
 * as on the first. Nobody wants to discover a drifted ticket id on stage.
 */
final class DemoDatabase
{
    public static function reset(): void
    {
        self::ensureSqliteFileExists();

        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    }

    public static function ensureSqliteFileExists(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || $database === ':memory:' || $database === '') {
            return;
        }

        if (is_file($database)) {
            return;
        }

        if (! is_dir(dirname($database))) {
            mkdir(dirname($database), 0755, true);
        }

        touch($database);
    }
}
