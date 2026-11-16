<?php

namespace Tests\Unit\Pricing;

use App\Pricing\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[Test]
    public function it_normalises_to_eight_decimals(): void
    {
        $this->assertSame('0.05000000', (string) Money::of('0.05'));
        $this->assertSame('3.00000000', (string) Money::of(3));
        $this->assertSame('0.00000000', (string) Money::zero());
    }

    #[Test]
    public function it_rejects_anything_that_is_not_a_decimal_string(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('1e-5');
    }

    #[Test]
    public function it_adds_and_multiplies_exactly(): void
    {
        $this->assertSame('0.00016000', (string) Money::of('0.000005')->multiply(32));
        $this->assertSame('0.04240000', (string) Money::of('0.00016')->add(Money::of('0.04224')));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function markupCases(): array
    {
        return [
            'exact result is unchanged' => ['0.04240000', '0.05300000'],
            'fraction below the scale rounds up' => ['0.00000001', '0.00000002'],
            'never rounds down' => ['0.00000003', '0.00000004'],
            'zero stays zero' => ['0', '0.00000000'],
        ];
    }

    #[Test]
    #[DataProvider('markupCases')]
    public function it_marks_up_rounding_towards_the_house(string $amount, string $expected): void
    {
        $this->assertSame($expected, (string) Money::of($amount)->markedUp('1.25'));
    }

    #[Test]
    public function it_refuses_to_mark_up_a_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('-1')->markedUp('1.25');
    }

    #[Test]
    public function it_compares_by_value(): void
    {
        $this->assertTrue(Money::of('0.1')->equals(Money::of('0.10000000')));
        $this->assertTrue(Money::of('0')->isZero());
        $this->assertTrue(Money::of('-0.00000001')->isNegative());
    }

    #[Test]
    public function it_serialises_to_a_decimal_string(): void
    {
        $this->assertSame('{"price":"0.05300000"}', json_encode(['price' => Money::of('0.053')]));
    }
}
