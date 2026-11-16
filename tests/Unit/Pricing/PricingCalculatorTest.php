<?php

namespace Tests\Unit\Pricing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Pricing\Money;
use App\Pricing\PricingCalculator;
use App\Services\Pixelforge\Data\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PricingCalculatorTest extends TestCase
{
    private PricingCalculator $pricing;

    protected function setUp(): void
    {
        // The real rate table, mirroring GET /v1/pricing. Inlined rather than
        // read from config so these tests pin the arithmetic itself.
        $this->pricing = new PricingCalculator([
            'markup' => '1.25',
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'rate_unit_tokens' => 1_000_000,
            'estimate_tokens' => [
                'characters_per_text_token' => 4,
                'per_reference_image' => 765,
                'output' => [
                    'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
                ],
                'reasoning' => [
                    'low' => ['min' => 0, 'max' => 500],
                    'medium' => ['min' => 200, 'max' => 2000],
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
        ]);
    }

    /**
     * The number this whole exercise turns on: our provider cost must equal
     * what Pixelforge reports. These are the real figures from a generation
     * run against the live API on 2026-11-16 (gen_01M7JXVTM098MKX00V6JFJ1EJ7),
     * whose GET /v1/usage row read exactly "0.012785000".
     */
    #[Test]
    public function it_reproduces_the_cost_pixelforge_reports(): void
    {
        $usage = new TokenUsage(
            inputTextTokens: 12,
            inputImageTokens: 0,
            outputTokens: 280,
            reasoningTokens: 122,
            totalTokens: 414,
        );

        $this->assertSame('0.012785000', (string) $this->pricing->providerCostForUsage($usage));
    }

    #[Test]
    public function the_user_pays_the_cost_plus_twenty_five_percent(): void
    {
        $cost = Money::of('0.012785');

        $this->assertSame('0.015981250', (string) $this->pricing->userPrice($cost));
    }

    /**
     * Each rate divided by 1,000,000 must survive as an exact per-token
     * amount. A single token of the oddest rate (12.50) is 0.0000125, and
     * billing it marks up to 0.000015625 — nine decimal places exactly.
     */
    #[Test]
    public function a_single_token_of_each_type_is_exact(): void
    {
        $this->assertSame('0.000005000', (string) $this->pricing->providerCost(['input_text_tokens' => 1]));
        $this->assertSame('0.000010000', (string) $this->pricing->providerCost(['input_image_tokens' => 1]));
        $this->assertSame('0.000040000', (string) $this->pricing->providerCost(['output_tokens' => 1]));
        $this->assertSame('0.000012500', (string) $this->pricing->providerCost(['reasoning_tokens' => 1]));

        $this->assertSame(
            '0.000015625',
            (string) $this->pricing->userPrice($this->pricing->providerCost(['reasoning_tokens' => 1])),
        );
    }

    /**
     * Guards the order of operations: tokens x rate is computed before the
     * division by 1,000,000, so a large token count cannot be truncated by an
     * intermediate rounding step.
     */
    #[Test]
    public function large_token_counts_stay_exact(): void
    {
        $this->assertSame('0.344000000', (string) $this->pricing->providerCost(['output_tokens' => 8600]));
        $this->assertSame('0.100000000', (string) $this->pricing->providerCost(['reasoning_tokens' => 8000]));
    }

    #[Test]
    public function the_estimate_uses_the_midpoint_of_the_reasoning_range(): void
    {
        // 1024x1024 low: 280 output, reasoning midpoint 250, prompt "" -> 0 text tokens.
        // 280 x 40/1M = 0.0112 ; 250 x 12.50/1M = 0.003125 ; total 0.014325
        $estimate = $this->pricing->estimate(ImageSize::Square, ImageQuality::Low);

        $this->assertSame('0.014325000', (string) $estimate->providerCost);
        $this->assertSame('0.017906250', (string) $estimate->userPrice);
    }

    #[Test]
    public function the_prompt_contributes_text_tokens_at_four_characters_each(): void
    {
        // 8 characters -> 2 text tokens -> 2 x 5/1M = 0.00001 on top.
        $bare = $this->pricing->estimate(ImageSize::Square, ImageQuality::Low);
        $withPrompt = $this->pricing->estimate(ImageSize::Square, ImageQuality::Low, prompt: 'abcdefgh');

        $this->assertSame('0.014335000', (string) $withPrompt->providerCost);
        $this->assertSame('0.014325000', (string) $bare->providerCost);
    }

    #[Test]
    public function each_reference_image_adds_765_input_image_tokens(): void
    {
        // 765 x 10/1M = 0.00765 per image.
        $estimate = $this->pricing->estimate(ImageSize::Square, ImageQuality::Low, referenceImages: 2);

        $this->assertSame('0.029625000', (string) $estimate->providerCost);
    }

    /**
     * @return array<string, array{ImageSize, ImageQuality, string}>
     */
    public static function qualityCases(): array
    {
        return [
            // output x 40/1M + reasoning midpoint x 12.50/1M
            'low square' => [ImageSize::Square, ImageQuality::Low, '0.014325000'],
            'medium square' => [ImageSize::Square, ImageQuality::Medium, '0.057750000'],
            'high square' => [ImageSize::Square, ImageQuality::High, '0.228250000'],
            'high landscape' => [ImageSize::Landscape, ImageQuality::High, '0.314250000'],
        ];
    }

    #[Test]
    #[DataProvider('qualityCases')]
    public function it_estimates_every_size_and_quality(ImageSize $size, ImageQuality $quality, string $expected): void
    {
        $this->assertSame($expected, (string) $this->pricing->estimate($size, $quality)->providerCost);
    }

    #[Test]
    public function it_rejects_token_types_it_has_no_rate_for(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pricing->providerCost(['video_tokens' => 10]);
    }

    #[Test]
    public function a_failed_generation_costs_nothing(): void
    {
        $this->assertTrue($this->pricing->userPrice(Money::zero())->isZero());
    }
}
