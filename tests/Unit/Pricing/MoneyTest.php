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
    public function it_normalises_to_nine_decimals(): void
    {
        $this->assertSame('0.050000000', (string) Money::of('0.05'));
        $this->assertSame('3.000000000', (string) Money::of(3));
        $this->assertSame('0.000000000', (string) Money::zero());
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
        $this->assertSame('0.000160000', (string) Money::of('0.000005')->multiply(32));
        $this->assertSame('0.042400000', (string) Money::of('0.00016')->add(Money::of('0.04224')));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function markupCases(): array
    {
        return [
            'exact result is unchanged' => ['0.042400000', '0.053000000'],
            // One reasoning token: 0.0000125 x 1.25 = 0.000015625, exact at 9dp.
            'nine decimals are exact' => ['0.0000125', '0.000015625'],
            // Below the scale, the remainder must push the price UP so the
            // markup never settles below our own cost.
            'fraction below the scale rounds up' => ['0.000000001', '0.000000002'],
            'never rounds down' => ['0.000000003', '0.000000004'],
            'zero stays zero' => ['0', '0.000000000'],
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
        $this->assertSame('{"price":"0.053000000"}', json_encode(['price' => Money::of('0.053')]));
    }
}
