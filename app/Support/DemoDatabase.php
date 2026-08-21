<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Determinism helper.
 *
 * Commands that write to the database (tickets, refunds) reset to the seeded
 * state first, so the same command produces the same output on the tenth run
 * as on the first. Nobody wants to discover a drifted ticket id on stage.
 */
final class DemoDatabase
{
    /**
     * For read-only commands. Seeds only if there is nothing there yet, so the
     * repository works immediately after a clone and a command never wipes data
     * it did not need to wipe.
     */
    public static function ensure(): void
    {
        self::ensureSqliteFileExists();

        if (Schema::hasTable('customers') && DB::table('customers')->exists()) {
            return;
        }

        self::reset();
    }

    /**
     * For commands that write. Resets to the seeded state first so the same
     * command produces the same ticket ids on the tenth run as on the first.
     */
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
