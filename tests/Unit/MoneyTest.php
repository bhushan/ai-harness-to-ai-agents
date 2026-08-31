<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_formats_whole_rupees(): void
    {
        $this->assertSame('₹999', Money::inr(99900));
    }

    public function test_it_groups_thousands(): void
    {
        $this->assertSame('₹50,000', Money::inr(5000000));
    }

    public function test_it_keeps_paise_when_present(): void
    {
        $this->assertSame('₹12.50', Money::inr(1250));
    }
}
