<?php

namespace App\AI\Tools;

/**
 * Who is asking.
 *
 * The model is not a user. It acts on behalf of one, and it should never hold
 * more permissions than the person or process it is standing in for.
 */
final class Actor
{
    /**
     * @param  array<int, string>  $permissions
     */
    public function __construct(
        public readonly string $name,
        public readonly array $permissions,
    ) {}

    /**
     * The support assistant. It may read the account, open tickets, and ask for
     * a refund. It may not carry one out.
     */
    public static function assistant(): self
    {
        return new self('assistant', [
            'customers.read',
            'orders.read',
            'payments.read',
            'tickets.create',
            'payments.refund.request',
        ]);
    }

    /**
     * The human who approves refunds. Only this actor can execute one.
     */
    public static function billingLead(): self
    {
        return new self('billing-lead', [
            'customers.read',
            'orders.read',
            'payments.read',
            'tickets.create',
            'payments.refund.request',
            'payments.refund.execute',
        ]);
    }

    /**
     * Deliberately narrow, used to prove the check does something.
     */
    public static function readOnly(): self
    {
        return new self('read-only', [
            'customers.read',
            'orders.read',
            'payments.read',
        ]);
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, strict: true);
    }
}
