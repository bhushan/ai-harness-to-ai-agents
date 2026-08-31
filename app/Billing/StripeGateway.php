<?php

namespace App\Billing;

/**
 * The payment gateway seen from the inside of this application.
 *
 * As with the model transport, there is no HTTP implementation in this repo.
 * Objects come back in the real Stripe shape, from committed fixtures.
 */
interface StripeGateway
{
    /** @return array<string, mixed> */
    public function retrievePaymentIntent(string $id): array;

    /** @return array<string, mixed> */
    public function retrieveCharge(string $id): array;

    /** @return array<string, mixed> */
    public function createRefund(string $chargeId, int $amountPaise): array;
}
