<?php

namespace App\AI\Tools;

use App\Models\Customer;
use Illuminate\Support\Facades\Log;

final class GetCustomer implements Tool
{
    public function name(): string
    {
        return 'get_customer';
    }

    public function impact(): ToolImpact
    {
        return ToolImpact::Read;
    }

    public function permission(): string
    {
        return 'customers.read';
    }

    public function approvalPermission(): ?string
    {
        return null;
    }

    public function description(): string
    {
        return 'Look up one customer by id. Returns their name, email, phone, plan and the date '
            .'they joined. Use this first when you need to know who you are dealing with. It does '
            .'not return orders or payments.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_id' => [
                    'type' => 'integer',
                    'description' => 'The id of the customer to look up.',
                ],
            ],
            'required' => ['customer_id'],
        ];
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function execute(array $input): ToolResult
    {
        $customer = Customer::find($input['customer_id']);

        Log::info('tool.get_customer', [
            'customer_id' => $input['customer_id'],
            'found' => $customer !== null,
        ]);

        if ($customer === null) {
            return ToolResult::failed('No customer with id '.$input['customer_id'].'.');
        }

        return ToolResult::ok([
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'plan' => $customer->plan,
            'customer_since' => $customer->joined_at->toDateString(),
        ], $customer->name.', '.$customer->plan.' plan');
    }
}
