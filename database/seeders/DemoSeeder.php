<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Three customers, one story each.
 *
 *  1. Priya Sharma  - charged twice for the same order, three seconds apart.
 *                     This is the incident the whole talk investigates.
 *  2. Arjun Mehta   - also has two payments, but they are two real purchases
 *                     months apart. Same tools, different conclusion.
 *  3. Vikram Nair   - one large enterprise payment, used to show what happens
 *                     when an agent reaches for a high impact action.
 *
 * Every id and every timestamp is fixed, so the same command prints the same
 * output every single time.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->priyaWasChargedTwice();
        $this->arjunBoughtTwiceOnPurpose();
        $this->vikramPaidEnterpriseInvoice();
    }

    private function priyaWasChargedTwice(): void
    {
        $joined = Carbon::parse('2026-05-14 09:12:00');

        Customer::create([
            'id' => 1,
            'name' => 'Priya Sharma',
            'email' => 'priya.sharma@example.in',
            'phone' => '+91 98200 11223',
            'plan' => 'Pro',
            'joined_at' => $joined,
            'created_at' => $joined,
            'updated_at' => $joined,
        ]);

        $placed = Carbon::parse('2026-08-19 10:14:58');

        Order::create([
            'id' => 1001,
            'customer_id' => 1,
            'reference' => 'ORD-2201',
            'description' => 'Pro plan, annual',
            'amount_paise' => 99900,
            'status' => 'paid',
            'placed_at' => $placed,
            'created_at' => $placed,
            'updated_at' => $placed,
        ]);

        // The double charge. One order, two successful payments, three seconds
        // apart, each with its own idempotency key on the gateway side.
        $this->payment(
            id: 123,
            orderId: 1001,
            customerId: 1,
            amountPaise: 99900,
            paidAt: '2026-08-19 10:15:02',
            intent: 'pi_3PriyaSharmaAA0001',
            charge: 'ch_3PriyaSharmaAA0001',
        );

        $this->payment(
            id: 124,
            orderId: 1001,
            customerId: 1,
            amountPaise: 99900,
            paidAt: '2026-08-19 10:15:05',
            intent: 'pi_3PriyaSharmaAA0002',
            charge: 'ch_3PriyaSharmaAA0002',
        );
    }

    private function arjunBoughtTwiceOnPurpose(): void
    {
        $joined = Carbon::parse('2026-02-03 18:41:00');

        Customer::create([
            'id' => 2,
            'name' => 'Arjun Mehta',
            'email' => 'arjun.mehta@example.in',
            'phone' => '+91 99300 55440',
            'plan' => 'Pro',
            'joined_at' => $joined,
            'created_at' => $joined,
            'updated_at' => $joined,
        ]);

        $first = Carbon::parse('2026-06-02 11:03:41');

        Order::create([
            'id' => 1002,
            'customer_id' => 2,
            'reference' => 'ORD-1876',
            'description' => 'Pro plan, annual',
            'amount_paise' => 99900,
            'status' => 'paid',
            'placed_at' => $first,
            'created_at' => $first,
            'updated_at' => $first,
        ]);

        $second = Carbon::parse('2026-08-11 16:22:07');

        Order::create([
            'id' => 1003,
            'customer_id' => 2,
            'reference' => 'ORD-2340',
            'description' => 'Extra seats, 5 users',
            'amount_paise' => 149900,
            'status' => 'paid',
            'placed_at' => $second,
            'created_at' => $second,
            'updated_at' => $second,
        ]);

        // Two payments, but two different orders, seventy days apart.
        $this->payment(
            id: 201,
            orderId: 1002,
            customerId: 2,
            amountPaise: 99900,
            paidAt: '2026-06-02 11:03:44',
            intent: 'pi_3ArjunMehtaBB0001',
            charge: 'ch_3ArjunMehtaBB0001',
        );

        $this->payment(
            id: 202,
            orderId: 1003,
            customerId: 2,
            amountPaise: 149900,
            paidAt: '2026-08-11 16:22:10',
            intent: 'pi_3ArjunMehtaBB0002',
            charge: 'ch_3ArjunMehtaBB0002',
        );
    }

    private function vikramPaidEnterpriseInvoice(): void
    {
        $joined = Carbon::parse('2025-11-27 10:00:00');

        Customer::create([
            'id' => 3,
            'name' => 'Vikram Nair',
            'email' => 'vikram.nair@example.in',
            'phone' => '+91 90040 77812',
            'plan' => 'Enterprise',
            'joined_at' => $joined,
            'created_at' => $joined,
            'updated_at' => $joined,
        ]);

        $placed = Carbon::parse('2026-08-18 09:30:00');

        Order::create([
            'id' => 1004,
            'customer_id' => 3,
            'reference' => 'ORD-2402',
            'description' => 'Enterprise plan, annual',
            'amount_paise' => 5000000,
            'status' => 'paid',
            'placed_at' => $placed,
            'created_at' => $placed,
            'updated_at' => $placed,
        ]);

        $this->payment(
            id: 301,
            orderId: 1004,
            customerId: 3,
            amountPaise: 5000000,
            paidAt: '2026-08-18 09:30:12',
            intent: 'pi_3VikramNairCC0001',
            charge: 'ch_3VikramNairCC0001',
        );
    }

    private function payment(
        int $id,
        int $orderId,
        int $customerId,
        int $amountPaise,
        string $paidAt,
        string $intent,
        string $charge,
    ): void {
        $moment = Carbon::parse($paidAt);

        Payment::create([
            'id' => $id,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'amount_paise' => $amountPaise,
            'currency' => 'INR',
            'status' => 'succeeded',
            'gateway' => 'stripe',
            'gateway_payment_intent_id' => $intent,
            'gateway_charge_id' => $charge,
            'paid_at' => $moment,
            'created_at' => $moment,
            'updated_at' => $moment,
        ]);
    }
}
