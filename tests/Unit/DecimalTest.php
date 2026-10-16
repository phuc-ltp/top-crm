<?php

namespace Tests\Unit;

use App\Support\Decimal;
use PHPUnit\Framework\TestCase;

/** Số thập phân chính xác cho tiền (bước 3.1). */
class DecimalTest extends TestCase
{
    public function test_no_floating_point_drift(): void
    {
        $this->assertNotSame(0.3, 0.1 + 0.2);                              // số thực bị sai số
        $this->assertSame(0, Decimal::cmp(Decimal::add('0.1', '0.2'), '0.3'));
        $this->assertSame('0.30', Decimal::round(Decimal::add(0.1, 0.2)));
        $this->assertSame('1000000000000.01', Decimal::round(Decimal::add('999999999999.99', '0.02')));
    }

    public function test_rounding_is_half_away_from_zero(): void
    {
        $this->assertSame('1.01', Decimal::round('1.005'));
        $this->assertSame('1.00', Decimal::round('1.0049'));
        $this->assertSame('-1.01', Decimal::round('-1.005'));
        $this->assertSame('0.00', Decimal::round('-0.001'));
        $this->assertSame('12.3', Decimal::round('12.345', 1));
        $this->assertSame('12', Decimal::round('12.4', 0));
    }

    public function test_helpers(): void
    {
        $this->assertSame('0', Decimal::clamp('-5', 0));
        $this->assertSame('100', Decimal::clamp('150', 0, 100));
        $this->assertSame('3.00', Decimal::round(Decimal::sum(['1', '1.5', 0.5])));
        $this->assertSame('33.33', Decimal::round(Decimal::div('100', '3')));
        $this->assertSame('0', Decimal::of(null));
        $this->assertSame('0', Decimal::of(''));
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Decimal::of('12abc');
    }

    public function test_division_by_zero_is_rejected(): void
    {
        $this->expectException(\DivisionByZeroError::class);
        Decimal::div('1', '0');
    }
}
