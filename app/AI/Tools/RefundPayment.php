<?php

namespace App\AI\Tools;

use App\Billing\StripeGateway;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The one tool that moves money.
 *
 * Nothing about the class says "do not run me". The class is perfectly capable
 * of issuing the refund, and demo:approve calls exactly this code. What stops
 * an agent from using it is the impact declared here, enforced in ToolExecutor.
 */
final class RefundPayment implements Tool
{
    public function __construct(private readonly StripeGateway $gateway) {}

    public function name(): string
    {
        return 'refund_payment';
    }

    public function impact(): ToolImpact
    {
        return ToolImpact::HighImpact;
    }

    public function permission(): string
    {
        return 'payments.refund.request';
    }

    public function approvalPermission(): ?string
    {
        return 'payments.refund.execute';
    }

    public function description(): string
    {
        return 'Request a refund of a payment. This is a high impact action: calling it does not '
            .'move any money, it records a request for a human to approve. Say clearly in your reply '
            .'that the refund is pending approval rather than done.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'payment_id' => [
                    'type' => 'integer',
                    'description' => 'The id of the payment to refund.',
                ],
                'amount_paise' => [
                    'type' => 'integer',
                    'description' => 'How much to refund, in paise. 99900 is ₹999.',
                ],
                'reason' => [
                    'type' => 'string',
                    'enum' => ['duplicate', 'requested_by_customer', 'fraudulent'],
                    'description' => 'Why the refund is being asked for.',
                ],
            ],
            'required' => ['payment_id', 'amount_paise', 'reason'],
        ];
    }

    public function rules(): array
    {
        return [
            'payment_id' => ['required', 'integer', 'min:1'],
            'amount_paise' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'in:duplicate,requested_by_customer,fraudulent'],
        ];
    }

    public function execute(array $input): ToolResult
    {
        $payment = Payment::find($input['payment_id']);

        if ($payment === null) {
            return ToolResult::failed('No payment with id '.$input['payment_id'].'.');
        }

        if ($payment->isRefunded()) {
            return ToolResult::failed('Payment '.$payment->id.' has already been refunded.');
        }

        if ($input['amount_paise'] > $payment->amount_paise) {
            return ToolResult::failed(
                'Cannot refund '.Money::inr($input['amount_paise']).' against a payment of '
                .$payment->amountFormatted().'.'
            );
        }

        $refund = $this->gateway->createRefund($payment->gateway_charge_id, $input['amount_paise']);

        $payment->update([
            'status' => 'refunded',
            'refunded_at' => Carbon::parse('@'.$refund['created']),
            'refunded_amount_paise' => $refund['amount'],
            'gateway_refund_id' => $refund['id'],
        ]);

        Log::info('tool.refund_payment', [
            'payment_id' => $payment->id,
            'amount_paise' => $refund['amount'],
            'refund_id' => $refund['id'],
        ]);

        return ToolResult::ok([
            'payment_id' => $payment->id,
            'refunded' => Money::inr($refund['amount']),
            'refunded_amount_paise' => $refund['amount'],
            'refund_id' => $refund['id'],
            'gateway_status' => $refund['status'],
            'reason' => $refund['reason'],
        ], Money::inr($refund['amount']).' refunded, '.$refund['id']);
    }
}
