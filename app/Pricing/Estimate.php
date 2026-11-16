<?php

namespace App\Pricing;

/**
 * What a generation is expected to cost, before Pixelforge has run it.
 */
final readonly class Estimate
{
    public function __construct(
        public Money $providerCost,
        public Money $userPrice,
    ) {}
}
