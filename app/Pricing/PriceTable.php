<?php

namespace App\Pricing;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

/**
 * Every user price the composer can show, precomputed on the server so the
 * number on screen is always the one PricingCalculator would store.
 */
final readonly class PriceTable
{
    public function __construct(
        private PricingCalculator $pricing,
        private int $maxReferenceImages,
    ) {}

    /**
     * @return array<string, array<string, list<string>>> [size][quality][referenceImageCount] => user price
     */
    public function build(): array
    {
        $table = [];

        foreach (ImageSize::cases() as $size) {
            foreach (ImageQuality::cases() as $quality) {
                $table[$size->value][$quality->value] = array_map(
                    fn (int $referenceImages): string => (string) $this->pricing->estimate($size, $quality, $referenceImages)->userPrice,
                    range(0, $this->maxReferenceImages),
                );
            }
        }

        return $table;
    }
}
