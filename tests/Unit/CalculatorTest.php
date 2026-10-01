<?php

namespace Tests\Unit;

use App\Support\Calculator;
use PHPUnit\Framework\TestCase;

class CalculatorTest extends TestCase
{
    public function test_it_computes_arithmetic(): void
    {
        $calculator = new Calculator;

        $this->assertSame(8.0, $calculator->evaluate('2 + 2 * 3'));
        $this->assertSame(12.0, $calculator->evaluate('(2 + 2) * 3'));
        $this->assertSame(2.5, $calculator->evaluate('5 / 2'));
        $this->assertSame(8.0, $calculator->evaluate('2 ^ 3'));
        $this->assertSame(1.0, $calculator->evaluate('7 % 3'));
        $this->assertSame(-5.0, $calculator->evaluate('-10 + 5'));
    }

    public function test_it_supports_functions(): void
    {
        $calculator = new Calculator;

        $this->assertSame(3.0, $calculator->evaluate('sqrt(9)'));
        $this->assertSame(12.0, $calculator->evaluate('max(4, 12)'));
        $this->assertSame(4.0, $calculator->evaluate('abs(-4)'));
        $this->assertSame(3.14, $calculator->evaluate('round(pi, 2)'));
    }

    public function test_it_rejects_division_by_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Calculator)->evaluate('5 / 0');
    }

    public function test_it_rejects_unknown_functions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Calculator)->evaluate('exec("ls")');
    }

    public function test_it_rejects_php_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Calculator)->evaluate('system("whoami")');
    }
}