<?php

namespace Tests\Unit\Pricing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Pricing\Money;
use App\Pricing\PricingCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PricingCalculatorTest extends TestCase
{
    private PricingCalculator $pricing;

    protected function setUp(): void
    {
        // Deliberately not config/pricing.php: these tests pin the arithmetic,
        // not the rates, so swapping in Pixelforge's real table must not break them.
        $this->pricing = new PricingCalculator([
            'markup' => '1.25',
            'rates' => [
                'prompt' => '0.000005',
                'reference_image' => '0.00001',
                'output' => '0.00004',
            ],
            'estimate_tokens' => [
                'prompt' => 32,
                'reference_image' => 765,
                'output' => [
                    '1024x1024' => ['standard' => 1056, 'high' => 4160],
                    '1024x1536' => ['standard' => 1584, 'high' => 6240],
                    '1536x1024' => ['standard' => 1584, 'high' => 6240],
                ],
            ],
        ]);
    }

    #[Test]
    public function it_estimates_from_typical_token_counts(): void
    {
        // 32 × 0.000005 + 1056 × 0.00004 = 0.00016 + 0.04224
        $estimate = $this->pricing->estimate(ImageSize::Square, ImageQuality::Standard);

        $this->assertSame('0.04240000', (string) $estimate->providerCost);
        $this->assertSame('0.05300000', (string) $estimate->userPrice);
    }

    #[Test]
    public function reference_images_raise_the_estimate(): void
    {
        // 0.0424 + 765 × 0.00001 = 0.05005 → × 1.25 = 0.0625625
        $estimate = $this->pricing->estimate(ImageSize::Square, ImageQuality::Standard, referenceImages: 1);

        $this->assertSame('0.05005000', (string) $estimate->providerCost);
        $this->assertSame('0.06256250', (string) $estimate->userPrice);
    }

    #[Test]
    public function it_computes_provider_cost_from_reported_usage(): void
    {
        $cost = $this->pricing->providerCost(['prompt' => 41, 'reference_image' => 0, 'output' => 1337]);

        // 41 × 0.000005 + 1337 × 0.00004 = 0.000205 + 0.05348
        $this->assertSame('0.05368500', (string) $cost);
        $this->assertSame('0.06710625', (string) $this->pricing->userPrice($cost));
    }

    #[Test]
    public function it_rejects_token_types_it_has_no_rate_for(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pricing->providerCost(['video' => 10]);
    }

    #[Test]
    public function nothing_costs_nothing(): void
    {
        $this->assertTrue($this->pricing->userPrice(Money::zero())->isZero());
    }
}
