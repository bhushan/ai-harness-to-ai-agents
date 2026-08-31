<?php

namespace App\Billing;

/**
 * Reads Stripe objects from committed fixtures.
 *
 * The files are shaped like real charge, payment_intent and refund objects,
 * because half the point of the talk is that the payloads on screen are the
 * ones an engineer would actually see.
 */
final class FixtureStripeGateway implements StripeGateway
{
    public function __construct(private readonly string $fixturePath) {}

    public function retrievePaymentIntent(string $id): array
    {
        return $this->read('payment_intent', 'payment_intents', $id);
    }

    public function retrieveCharge(string $id): array
    {
        return $this->read('charge', 'charges', $id);
    }

    public function createRefund(string $chargeId, int $amountPaise): array
    {
        $refund = $this->read('refund', 'refunds', $chargeId);

        // The fixture holds the shape; the caller decides the amount, exactly as
        // it would against the live API.
        $refund['amount'] = $amountPaise;

        return $refund;
    }

    private function read(string $object, string $directory, string $id): array
    {
        $path = $this->fixturePath.'/'.$directory.'/'.$id.'.json';

        if (! is_file($path)) {
            throw MissingStripeFixtureException::for($object, $id, $path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw MissingStripeFixtureException::for($object, $id, $path);
        }

        return $decoded;
    }
}
