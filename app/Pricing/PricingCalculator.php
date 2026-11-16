<?php

namespace App\Pricing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use InvalidArgumentException;

/**
 * The single place that turns token usage into money.
 *
 * Provider cost  = Σ tokens × rate, reproducing Pixelforge's own number.
 * User price     = provider cost × markup, rounded up.
 */
final readonly class PricingCalculator
{
    /**
     * @param  array{
     *     markup: string,
     *     rates: array<string, string>,
     *     estimate_tokens: array{prompt: int, reference_image: int, output: array<string, array<string, int>>}
     * }  $config
     */
    public function __construct(private array $config) {}

    /**
     * The up-front estimate shown before a generation is submitted. Output
     * tokens are only known once it finishes, so typical values stand in.
     */
    public function estimate(ImageSize $size, ImageQuality $quality, int $referenceImages = 0): Estimate
    {
        $tokens = $this->config['estimate_tokens'];

        $providerCost = $this->providerCost([
            'prompt' => $tokens['prompt'],
            'reference_image' => $tokens['reference_image'] * $referenceImages,
            'output' => $tokens['output'][$size->value][$quality->value],
        ]);

        return new Estimate($providerCost, $this->userPrice($providerCost));
    }

    /**
     * What Pixelforge charges us for the given token usage.
     *
     * @param  array<string, int>  $usage  token counts keyed by token type
     */
    public function providerCost(array $usage): Money
    {
        $total = Money::zero();

        foreach ($usage as $type => $tokens) {
            $rate = $this->config['rates'][$type]
                ?? throw new InvalidArgumentException("Unknown token type [{$type}].");

            $total = $total->add(Money::of($rate)->multiply($tokens));
        }

        return $total;
    }

    /**
     * What the user pays for a given provider cost.
     */
    public function userPrice(Money $providerCost): Money
    {
        return $providerCost->markedUp($this->config['markup']);
    }
}
