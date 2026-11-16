<?php

namespace App\Pricing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Services\Pixelforge\Data\TokenUsage;
use InvalidArgumentException;

/**
 * The single place that turns token usage into money.
 *
 * Provider cost = Σ (tokens × rate ÷ 1,000,000), reproducing Pixelforge's own
 * figure exactly — verified against GET /v1/usage.
 * User price    = provider cost × markup, rounded up.
 */
final readonly class PricingCalculator
{
    /**
     * @param  array{
     *     markup: string,
     *     rates: array<string, string>,
     *     rate_unit_tokens: int,
     *     estimate_tokens: array{
     *         characters_per_text_token: int,
     *         per_reference_image: int,
     *         output: array<string, array<string, int>>,
     *         reasoning: array<string, array{min: int, max: int}>
     *     }
     * }  $config
     */
    public function __construct(private array $config) {}

    /**
     * The price shown before submitting.
     *
     * Input text and output tokens are predictable; reasoning tokens are not
     * known until Pixelforge finishes, so the midpoint of the documented range
     * stands in. That is precisely why this is an estimate.
     */
    public function estimate(
        ImageSize $size,
        ImageQuality $quality,
        int $referenceImages = 0,
        string $prompt = '',
    ): Estimate {
        $tokens = $this->config['estimate_tokens'];
        $reasoning = $tokens['reasoning'][$quality->value];

        $providerCost = $this->providerCost([
            'input_text_tokens' => $this->textTokens($prompt),
            'input_image_tokens' => $tokens['per_reference_image'] * $referenceImages,
            'output_tokens' => $tokens['output'][$quality->value][$size->value],
            // Midpoint: half the generations land above it, half below.
            'reasoning_tokens' => intdiv($reasoning['min'] + $reasoning['max'], 2),
        ]);

        return new Estimate($providerCost, $this->userPrice($providerCost));
    }

    /**
     * What Pixelforge charges us for usage it has reported.
     */
    public function providerCostForUsage(TokenUsage $usage): Money
    {
        return $this->providerCost($usage->billable());
    }

    /**
     * @param  array<string, int>  $tokensByType
     */
    public function providerCost(array $tokensByType): Money
    {
        $perUnit = $this->config['rate_unit_tokens'];
        $total = Money::zero();

        foreach ($tokensByType as $type => $tokens) {
            $rate = $this->config['rates'][$type]
                ?? throw new InvalidArgumentException("Unknown token type [{$type}].");

            // Rates are per 1,000,000 tokens, so divide after multiplying to
            // keep the intermediate value exact.
            $total = $total->add(Money::of($rate)->multiply($tokens)->divide($perUnit));
        }

        return $total;
    }

    public function userPrice(Money $providerCost): Money
    {
        return $providerCost->markedUp($this->config['markup']);
    }

    /**
     * Pixelforge bills roughly one token per four characters.
     */
    private function textTokens(string $prompt): int
    {
        $perToken = max(1, $this->config['estimate_tokens']['characters_per_text_token']);

        return (int) ceil(mb_strlen($prompt) / $perToken);
    }
}
