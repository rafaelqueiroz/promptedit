<?php

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'prompt' => $this->prompt,
            'size' => $this->size,
            'quality' => $this->quality,
            // Decimal strings all the way to the browser; the UI formats them
            // for display and never does arithmetic on them.
            'estimatedPrice' => $this->estimated_price,
            'price' => $this->price,
            'imageUrl' => $this->imageUrl(),
            'isStored' => $this->isStored(),
            'errorMessage' => $this->error_message,
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
            'completedAt' => $this->completed_at?->toIso8601ZuluString(),
        ];
    }
}
