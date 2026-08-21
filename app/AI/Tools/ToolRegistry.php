<?php

namespace App\AI\Tools;

/**
 * The list of tools this application is willing to expose.
 *
 * Registration order is the order the model sees, and it stays stable so the
 * request payload is byte for byte the same on every run.
 */
final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(Tool $tool): self
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): Tool
    {
        return $this->tools[$name] ?? throw UnknownToolException::named($name, array_keys($this->tools));
    }

    /**
     * @return array<string, Tool>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * The tools array of a Messages API request.
     *
     * @return array<int, array<string, mixed>>
     */
    public function schemas(): array
    {
        return array_values(array_map(fn (Tool $tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'input_schema' => $tool->inputSchema(),
        ], $this->tools));
    }
}
