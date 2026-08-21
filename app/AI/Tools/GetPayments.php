<?php

namespace App\AI\Tools;

use App\Billing\StripeGateway;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

final class GetPayments implements Tool
{
    public function __construct(private readonly StripeGateway $gateway) {}

    public function name(): string
    {
        return 'get_payments';
    }

    public function description(): string
    {
        return 'List every payment taken from one customer, oldest first. Returns the amount, the '
            .'status, the order it was taken against, the exact time it settled, and the gateway '
            .'record for it including the idempotency key the checkout sent. Two payments that '
            .'share an order but not an idempotency key were submitted twice, rather than retried '
            .'once.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_id' => [
                    'type' => 'integer',
                    'description' => 'The id of the customer whose payments should be listed.',
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

        $payments = Payment::with('order')
            ->where('customer_id', $input['customer_id'])
            ->orderBy('paid_at')
            ->get();

        Log::info('tool.get_payments', [
            'customer_id' => $input['customer_id'],
            'payments' => $payments->count(),
        ]);

        return ToolResult::ok([
            'customer_id' => (int) $input['customer_id'],
            'payments' => $payments->map(function (Payment $payment) {
                // A real gateway lookup, through the gateway interface. The
                // transport underneath is fixture backed; the call is not faked.
                $intent = $this->gateway->retrievePaymentIntent($payment->gateway_payment_intent_id);

                return [
                    'id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'order_reference' => $payment->order->reference,
                    'amount' => $payment->amountFormatted(),
                    'amount_paise' => $payment->amount_paise,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'paid_at' => $payment->paid_at->toIso8601String(),
                    'gateway' => [
                        'name' => $payment->gateway,
                        'payment_intent' => $intent['id'],
                        'charge' => $payment->gateway_charge_id,
                        'idempotency_key' => $intent['metadata']['idempotency_key'],
                        'client_ip' => $intent['metadata']['client_ip'],
                        'submitted_from' => $intent['metadata']['submitted_from'],
                    ],
                ];
            })->all(),
        ], $payments->count().' payment(s)');
    }
}
