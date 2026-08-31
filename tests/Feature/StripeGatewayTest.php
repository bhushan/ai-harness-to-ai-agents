<?php

namespace Tests\Feature;

use App\Billing\FixtureStripeGateway;
use App\Billing\MissingStripeFixtureException;
use Tests\TestCase;

class StripeGatewayTest extends TestCase
{
    public function test_it_reads_a_charge_shaped_like_a_real_stripe_object(): void
    {
        $charge = $this->gateway()->retrieveCharge('ch_3PriyaSharmaAA0001');

        $this->assertSame('charge', $charge['object']);
        $this->assertSame(99900, $charge['amount']);
        $this->assertSame('inr', $charge['currency']);
        $this->assertSame('succeeded', $charge['status']);
    }

    public function test_it_reads_a_payment_intent_shaped_like_a_real_stripe_object(): void
    {
        $intent = $this->gateway()->retrievePaymentIntent('pi_3PriyaSharmaAA0001');

        $this->assertSame('payment_intent', $intent['object']);
        $this->assertSame('succeeded', $intent['status']);
        $this->assertArrayHasKey('idempotency_key', $intent['metadata']);
    }

    public function test_the_two_hero_payments_used_different_idempotency_keys(): void
    {
        $first = $this->gateway()->retrievePaymentIntent('pi_3PriyaSharmaAA0001');
        $second = $this->gateway()->retrievePaymentIntent('pi_3PriyaSharmaAA0002');

        $this->assertNotSame(
            $first['metadata']['idempotency_key'],
            $second['metadata']['idempotency_key'],
            'The double charge is two separate requests, not one retried request.'
        );
    }

    public function test_it_fails_loudly_when_a_stripe_fixture_is_missing(): void
    {
        $this->expectException(MissingStripeFixtureException::class);
        $this->expectExceptionMessageMatches('/ch_does_not_exist/');

        $this->gateway()->retrieveCharge('ch_does_not_exist');
    }

    private function gateway(): FixtureStripeGateway
    {
        return new FixtureStripeGateway(base_path('tests/fixtures/stripe'));
    }
}
