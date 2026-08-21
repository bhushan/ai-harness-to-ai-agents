<?php

namespace App\AI\Tools;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs a tool the model asked for.
 *
 * Everything that must happen around every tool call lives here, once:
 * look up the tool, validate the input the model produced, run it, and turn a
 * failure into a result the model can read rather than an exception that kills
 * the run. The model is an untrusted caller. Treat it like one.
 */
final class ToolExecutor
{
    public function __construct(private readonly ToolRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function run(string $name, array $input): ToolResult
    {
        if (! $this->registry->has($name)) {
            return ToolResult::failed('There is no tool named ['.$name.'].');
        }

        $tool = $this->registry->get($name);

        try {
            $validated = Validator::make($input, $tool->rules())->validate();
        } catch (ValidationException $exception) {
            return ToolResult::failed(implode(' ', $exception->validator->errors()->all()));
        }

        try {
            return $tool->execute($validated);
        } catch (Throwable $exception) {
            // A tool that blows up is a bad tool_result, not a dead conversation.
            return ToolResult::failed($exception->getMessage());
        }
    }
}
