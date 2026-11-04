<?php

namespace Tests\Unit;

use App\Support\Rupiah;
use Tests\TestCase;

class RupiahTest extends TestCase
{
    public function test_format(): void
    {
        $this->assertSame('Rp 1.000.000', Rupiah::format('1000000.00'));
        $this->assertSame('Rp 53.450.000', Rupiah::format('53450000'));
        $this->assertSame('Rp 0', Rupiah::format(null));
        $this->assertSame('Rp 1.001', Rupiah::format('1000.50'));   // bulatkan saat tampil
        $this->assertSame('−Rp 2.500.000', Rupiah::format('-2500000'));
        $this->assertSame('Rp 9.999.999.999.999', Rupiah::format('9999999999999.00'));
    }

    public function test_parse(): void
    {
        $this->assertSame('1250000.00', Rupiah::parse('Rp 1.250.000'));
        $this->assertSame('1250000.50', Rupiah::parse('1.250.000,50'));
        $this->assertSame('-500.00', Rupiah::parse('-500'));
        $this->assertNull(Rupiah::parse('abc'));
        $this->assertNull(Rupiah::parse(''));
    }

    public function test_percent_and_short(): void
    {
        $this->assertSame('15%', Rupiah::percent('15.00'));
        $this->assertSame('12,5%', Rupiah::percent('12.50'));
        $this->assertSame('Rp 1,2 jt', Rupiah::short(1_240_000));
        $this->assertSame('Rp 80 jt', Rupiah::short('80000000'));
    }
}
