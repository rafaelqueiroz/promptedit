<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Downloads the generated image into our own storage.
 *
 * Pixelforge's URLs are signed and expire 24 hours after completion, so
 * without this the deployed app would show broken images the next day.
 */
class StoreMediaFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly string $mediaId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        $media = Media::query()->whereKey($this->mediaId)->first();

        if (! $media instanceof Media || $media->isStored() || $media->source_url === null) {
            return;
        }

        $response = Http::timeout(30)->get($media->source_url);

        if (! $response->successful()) {
            // Past its expiry there is nothing left to fetch; retrying would
            // only burn attempts.
            if ($media->source_url_expires_at?->isPast() === true) {
                Log::warning('Image URL expired before it could be stored.', ['media_id' => $media->getKey()]);

                return;
            }

            $response->throw();
        }

        $path = 'media/'.$media->getKey().'.png';

        Storage::put($path, $response->body());

        $media->forceFill(['disk' => $disk, 'path' => $path])->save();
    }
}
