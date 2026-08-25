<?php

namespace App\AI\Tools;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

final class GetOrders implements Tool
{
    public function name(): string
    {
        return 'get_orders';
    }

    public function impact(): ToolImpact
    {
        return ToolImpact::Read;
    }

    public function permission(): string
    {
        return 'orders.read';
    }

    public function approvalPermission(): ?string
    {
        return null;
    }

    public function description(): string
    {
        return 'List every order placed by one customer, newest first. Returns the order reference, '
            .'what was bought, the amount and when it was placed. Use this to find out what a '
            .'payment was actually for, and whether two payments belong to one order or two.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_id' => [
                    'type' => 'integer',
                    'description' => 'The id of the customer whose orders should be listed.',
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
        if (! Customer::whereKey($input['customer_id'])->exists()) {
            return ToolResult::failed('No customer with id '.$input['customer_id'].'.');
        }

        $orders = Order::where('customer_id', $input['customer_id'])
            ->orderByDesc('placed_at')
            ->get();

        Log::info('tool.get_orders', [
            'customer_id' => $input['customer_id'],
            'orders' => $orders->count(),
        ]);

        return ToolResult::ok([
            'customer_id' => (int) $input['customer_id'],
            'orders' => $orders->map(fn (Order $order) => [
                'id' => $order->id,
                'reference' => $order->reference,
                'description' => $order->description,
                'amount' => $order->amountFormatted(),
                'amount_paise' => $order->amount_paise,
                'status' => $order->status,
                'placed_at' => $order->placed_at->toIso8601String(),
            ])->all(),
        ], $orders->count().' order(s)');
    }
}
