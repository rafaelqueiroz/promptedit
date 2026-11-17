<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Media;
use App\Pricing\Money;
use App\Services\Pixelforge\Exceptions\PixelforgeException;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hands a generation to Pixelforge.
 *
 * Retries live here rather than in the client or Horizon, so there is a single
 * retry policy. Pixelforge's Idempotency-Key makes a retry safe: a replayed
 * request returns the original generation instead of creating a second one.
 */
class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** Give up well before Pixelforge's daily limits would matter. */
    public int $timeout = 30;

    public function __construct(public readonly string $mediaId)
    {
        $this->onQueue('generations');
    }

    /**
     * Falls back to this when the API does not send Retry-After.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(PixelforgeClient $client): void
    {
        $media = Media::query()->whereKey($this->mediaId)->first();

        // Already submitted or settled: nothing to do. Guards against a
        // duplicate dispatch.
        if (! $media instanceof Media || $media->provider_id !== null || $media->status !== GenerationStatus::Queued) {
            return;
        }

        try {
            $generation = $client->createGeneration(
                prompt: $media->prompt,
                size: $media->size,
                quality: $media->quality,
                referenceImages: $this->referenceImages($media),
                metadata: ['media_id' => $media->getKey()],
                // Our own id: stable across retries, unique across generations.
                idempotencyKey: $media->getKey(),
            );
        } catch (PixelforgeException $e) {
            $this->handleFailure($media, $e);

            return;
        }

        $media->provider_id = $generation->id;
        $media->submitted_at = now();

        // A webhook can arrive before this request even returns, so only move
        // the status forward if nothing else already has.
        if ($media->status->canTransitionTo($generation->status)) {
            $media->status = $generation->status;
        }

        $media->save();
    }

    /**
     * @throws PixelforgeException
     */
    private function handleFailure(Media $media, PixelforgeException $e): void
    {
        // A permanent error will fail identically forever, so fail it now and
        // charge nothing. Retryable errors are rethrown for the queue to
        // handle, honouring Retry-After when Pixelforge sent one.
        if (! $e->isRetryable()) {
            $this->fail($media, $e->errorCode, $e->userMessage());

            return;
        }

        if ($this->attempts() >= $this->tries) {
            $this->fail($media, $e->errorCode, $e->userMessage());

            return;
        }

        $retryAfter = $e->retryAfter();

        if ($retryAfter !== null) {
            $this->release($retryAfter);

            return;
        }

        throw $e;
    }

    private function fail(Media $media, string $code, string $message): void
    {
        $media->forceFill([
            'status' => GenerationStatus::Failed,
            // Never submitted, so it never cost anything.
            'provider_cost' => (string) Money::zero(),
            'price' => (string) Money::zero(),
            'error_code' => $code,
            'error_message' => $message,
            'completed_at' => now(),
        ])->save();
    }

    /**
     * @return list<string>
     */
    private function referenceImages(Media $media): array
    {
        /** @var list<string> $images */
        $images = $media->data['reference_images'] ?? [];

        return $images;
    }

    /**
     * The queue has exhausted its retries.
     */
    public function failed(?Throwable $e): void
    {
        $media = Media::query()->whereKey($this->mediaId)->first();

        if (! $media instanceof Media || $media->status->isTerminal()) {
            return;
        }

        Log::warning('Giving up on a generation submission.', [
            'media_id' => $this->mediaId,
            'error' => $e?->getMessage(),
        ]);

        $this->fail(
            $media,
            'submission_failed',
            'We could not reach Pixelforge. You were not charged.',
        );
    }
}
