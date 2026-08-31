<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Support\DemoDatabase;
use App\Support\DemoPrinter;
use Illuminate\Console\Command;

class DemoDataCommand extends Command
{
    protected $signature = 'demo:data';

    protected $description = 'Reset the SQLite database to the seeded demo scenario and print it';

    public function handle(): int
    {
        $printer = DemoPrinter::for($this->output);

        DemoDatabase::reset();

        $printer->title('Demo data', 'SQLite, seeded from database/seeders/DemoSeeder.php');

        $printer->section('CUSTOMERS');
        $printer->table(
            ['ID', 'NAME', 'EMAIL', 'PLAN'],
            Customer::orderBy('id')->get()
                ->map(fn (Customer $customer) => [
                    $customer->id,
                    $customer->name,
                    $customer->email,
                    $customer->plan,
                ])->all()
        );

        $printer->section('ORDERS');
        $printer->table(
            ['ID', 'CUSTOMER', 'REFERENCE', 'DESCRIPTION', 'AMOUNT', 'PLACED AT'],
            Order::with('customer')->orderBy('id')->get()
                ->map(fn (Order $order) => [
                    $order->id,
                    $order->customer->name,
                    $order->reference,
                    $order->description,
                    $order->amountFormatted(),
                    $order->placed_at->format('d M H:i:s'),
                ])->all()
        );

        $printer->section('PAYMENTS');
        $printer->table(
            ['ID', 'CUSTOMER', 'ORDER', 'AMOUNT', 'STATUS', 'PAID AT'],
            Payment::with(['customer', 'order'])->orderBy('id')->get()
                ->map(fn (Payment $payment) => [
                    $payment->id,
                    $payment->customer->name,
                    $payment->order->reference,
                    $payment->amountFormatted(),
                    $payment->status,
                    $payment->paid_at->format('d M H:i:s'),
                ])->all()
        );

        $printer->section('WHAT TO NOTICE');
        $printer->bullet('Payments 123 and 124 are both ₹999, both succeeded, both against');
        $printer->note('   order ORD-2201, three seconds apart. That is the double charge.');
        $printer->blank();
        $printer->bullet('Arjun Mehta also has two payments, but against two different orders');
        $printer->note('   seventy days apart. That is a repeat purchase, not a double charge.');
        $printer->blank();

        return self::SUCCESS;
    }
}
