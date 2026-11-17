<?php

namespace App\Services\Media;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Pricing\PriceTable;
use App\Pricing\PricingCalculator;

/**
 * Everything the prompt composer needs to render and price itself.
 *
 * The landing page and the generate page show the same composer, so they ask
 * for the same props here rather than each assembling their own.
 */
class ComposerOptions
{
    public function __construct(private readonly PricingCalculator $pricing) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $maxReferenceImages = $this->maxReferenceImages();

        return [
            'options' => [
                'sizes' => array_map(fn (ImageSize $size): array => $size->toOption(), ImageSize::cases()),
                'qualities' => array_map(fn (ImageQuality $quality): array => $quality->toOption(), ImageQuality::cases()),
            ],
            // Precomputed server-side for every size x quality x reference
            // image count, so the figure on screen is the one we would store.
            'estimates' => (new PriceTable($this->pricing, $maxReferenceImages))->build(),
            'maxReferenceImages' => $maxReferenceImages,
            'maxPromptCharacters' => (int) config('pixelforge.max_prompt_characters'),
            // Pixelforge's own default, and the sensible middle option.
            'defaults' => [
                'size' => ImageSize::Square->value,
                'quality' => ImageQuality::Medium->value,
            ],
        ];
    }

    private function maxReferenceImages(): int
    {
        return (int) config('pixelforge.max_reference_images');
    }
}
