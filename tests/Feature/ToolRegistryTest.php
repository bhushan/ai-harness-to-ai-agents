<?php

namespace Tests\Feature;

use App\AI\Tools\ToolRegistry;
use App\AI\Tools\UnknownToolException;
use Tests\TestCase;

class ToolRegistryTest extends TestCase
{
    public function test_it_exposes_every_tool_in_the_shape_the_api_expects(): void
    {
        $schemas = app(ToolRegistry::class)->schemas();

        $this->assertSame(
            ['get_customer', 'get_orders', 'get_payments', 'create_ticket', 'refund_payment'],
            array_column($schemas, 'name')
        );

        foreach ($schemas as $schema) {
            $this->assertArrayHasKey('description', $schema);
            $this->assertArrayHasKey('input_schema', $schema);
            $this->assertSame('object', $schema['input_schema']['type']);
            $this->assertNotEmpty($schema['input_schema']['properties']);
        }
    }

    public function test_every_described_property_tells_the_model_what_it_is_for(): void
    {
        foreach (app(ToolRegistry::class)->schemas() as $schema) {
            foreach ($schema['input_schema']['properties'] as $name => $property) {
                $this->assertArrayHasKey(
                    'description',
                    $property,
                    $schema['name'].'.'.$name.' has no description, so the model is guessing.'
                );
            }
        }
    }

    public function test_asking_for_a_tool_that_does_not_exist_is_a_loud_failure(): void
    {
        $this->expectException(UnknownToolException::class);
        $this->expectExceptionMessageMatches('/delete_everything/');

        app(ToolRegistry::class)->get('delete_everything');
    }
}
