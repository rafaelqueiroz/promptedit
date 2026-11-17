<?php

namespace App\Services\Media;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Enums\MediaType;
use App\Jobs\SubmitGeneration;
use App\Models\Media;
use App\Models\User;
use App\Pricing\PricingCalculator;

/**
 * Creates a generation and hands it to the queue.
 *
 * The price is always computed here from the validated input, never taken
 * from the client: the estimate shown in the browser is a display of this
 * calculation, not an input to it.
 */
class MediaCreator
{
    public function __construct(private readonly PricingCalculator $pricing) {}

    /**
     * @param  list<string>  $referenceImages
     */
    public function create(
        User $user,
        string $prompt,
        ImageSize $size,
        ImageQuality $quality,
        array $referenceImages = [],
    ): Media {
        $estimate = $this->pricing->estimate($size, $quality, count($referenceImages), $prompt);

        $media = Media::query()->create([
            'user_id' => $user->id,
            'type' => MediaType::Image,
            'status' => GenerationStatus::Queued,
            'prompt' => $prompt,
            'size' => $size->value,
            'quality' => $quality->value,
            'estimated_provider_cost' => (string) $estimate->providerCost,
            'estimated_price' => (string) $estimate->userPrice,
            'data' => $referenceImages === [] ? null : ['reference_images' => $referenceImages],
        ]);

        // Submission is queued so provider rate limits and overloads are
        // retried with backoff instead of failing the user's request.
        dispatch(new SubmitGeneration($media->id));

        return $media;
    }
}
