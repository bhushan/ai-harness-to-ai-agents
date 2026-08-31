<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use Tests\TestCase;

class DemoDataCommandTest extends TestCase
{
    // No RefreshDatabase here on purpose: demo:data resets the schema itself,
    // and the wrapping transaction would block the vacuum that reset performs.

    public function test_it_prints_the_hero_scenario(): void
    {
        $this->artisan('demo:data')
            ->expectsOutputToContain('Priya Sharma')
            ->expectsOutputToContain('₹999')
            ->assertExitCode(0);
    }

    public function test_it_seeds_a_double_charge_three_seconds_apart(): void
    {
        $this->artisan('demo:data')->assertExitCode(0);

        $first = Payment::findOrFail(123);
        $second = Payment::findOrFail(124);

        $this->assertSame($first->order_id, $second->order_id);
        $this->assertSame($first->amount_paise, $second->amount_paise);
        $this->assertSame('succeeded', $first->status);
        $this->assertSame('succeeded', $second->status);
        $this->assertSame(3, (int) $first->paid_at->diffInSeconds($second->paid_at));
    }

    public function test_it_seeds_a_customer_whose_two_payments_are_legitimate(): void
    {
        $this->artisan('demo:data')->assertExitCode(0);

        $arjun = Customer::where('name', 'Arjun Mehta')->firstOrFail();
        $payments = $arjun->payments()->orderBy('id')->get();

        $this->assertCount(2, $payments);
        $this->assertNotSame(
            $payments[0]->order_id,
            $payments[1]->order_id,
            'Two payments against two different orders is a repeat purchase, not a double charge.'
        );
    }

    public function test_seeding_twice_produces_identical_data(): void
    {
        $this->artisan('demo:data')->assertExitCode(0);
        $before = Payment::orderBy('id')->get()->toJson();

        $this->artisan('demo:data')->assertExitCode(0);

        $this->assertSame($before, Payment::orderBy('id')->get()->toJson());
    }
}
