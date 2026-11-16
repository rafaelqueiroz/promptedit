<?php

namespace App\Http\Controllers;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Pricing\PriceTable;
use App\Pricing\PricingCalculator;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    private const array SAMPLE_PROMPTS = [
        'A lighthouse at dusk, long exposure, fog rolling over the rocks',
        'Isometric cutaway of a tiny ramen shop, warm light, paper texture',
        'Studio photo of a translucent glass chess knight on black slate',
        'Watercolor map of an imaginary archipelago with hand-lettered names',
    ];

    public function __invoke(PricingCalculator $pricing): Response
    {
        $maxReferenceImages = (int) config('pixelforge.max_reference_images');

        return Inertia::render('Welcome', [
            'options' => [
                'sizes' => array_map(fn (ImageSize $size): array => $size->toOption(), ImageSize::cases()),
                'qualities' => array_map(fn (ImageQuality $quality): array => $quality->toOption(), ImageQuality::cases()),
            ],
            'estimates' => (new PriceTable($pricing, $maxReferenceImages))->build(),
            'maxReferenceImages' => $maxReferenceImages,
            // Pixelforge's own default, and the sensible middle option.
            'defaults' => ['size' => ImageSize::Square->value, 'quality' => ImageQuality::Medium->value],
            'samplePrompts' => self::SAMPLE_PROMPTS,
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
        ]);
    }
}
