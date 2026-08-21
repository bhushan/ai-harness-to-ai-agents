<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * dd() with one concession to being testable.
 *
 * The walkthrough routes exist to be dumped on a projector, so on stage this is
 * a plain dd(). Under php artisan test it returns the section labels instead,
 * because dd() calls exit() and would take the test runner down with it.
 *
 * The array passed in is fully evaluated before this method is reached, so the
 * smoke test still proves every route can build everything it means to show.
 */
final class DemoDump
{
    /**
     * @param  array<string, mixed>  $sections
     */
    public static function these(array $sections): JsonResponse
    {
        if (app()->runningUnitTests()) {
            return response()->json(['sections' => array_keys($sections)]);
        }

        dd($sections);
    }
}
