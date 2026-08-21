<?php

namespace App\AI\Tools;

/**
 * One thing the model is allowed to ask this application to do.
 *
 * A tool is not a prompt trick. It is an ordinary Laravel service with a name,
 * a description written for the model to read, a JSON schema for its input,
 * validation rules for the same input, and a typed execute().
 */
interface Tool
{
    /**
     * The name the model calls. Lower snake case, verb first.
     */
    public function name(): string;

    /**
     * What this tool can do to the world. Decides whether it runs when asked,
     * or only ever gets requested.
     */
    public function impact(): ToolImpact;

    /**
     * The permission an actor must hold to call this tool at all.
     */
    public function permission(): string;

    /**
     * For high impact tools, the permission required to approve and actually
     * carry the action out. Null for everything that runs when asked.
     */
    public function approvalPermission(): ?string;

    /**
     * Written for the model, not for a README. Say what it returns and when to
     * reach for it, because this text is most of what decides whether the model
     * picks the right tool.
     */
    public function description(): string;

    /**
     * JSON Schema, exactly as it goes into the tools array of the request.
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * Laravel validation rules for the same input. The schema is what we tell
     * the model; these rules are what we actually enforce. Never trust the
     * first to do the job of the second.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * @param  array<string, mixed>  $input  Already validated against rules().
     */
    public function execute(array $input): ToolResult;
}
