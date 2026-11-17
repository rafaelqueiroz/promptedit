<?php

namespace App\Services\Media;

use App\Actions\Media\ApplyProviderResult;
use App\Models\Media;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\Data\ProviderGeneration;
use App\Services\Pixelforge\WebhookSignature;
use Illuminate\Support\Facades\Log;

/**
 * Turns a raw Pixelforge webhook delivery into a settled generation.
 *
 * Pixelforge delivers at least once, in any order, and expects a 2xx within
 * 10 seconds, so the work is deliberately small and synchronous: a row lock
 * plus two unique constraints make duplicates and races harmless.
 */
class PixelforgeWebhookProcessor
{
    private const string PROVIDER = 'pixelforge';

    public function __construct(
        private readonly WebhookSignature $signature,
        private readonly ApplyProviderResult $applyResult,
    ) {}

    /**
     * @param  string  $rawBody  the exact bytes received, which the signature covers
     */
    public function process(string $rawBody, ?string $signatureHeader): WebhookOutcome
    {
        // Verified against the raw body: re-encoding the parsed JSON would
        // change the bytes and break the signature.
        if (! $this->signature->isValid($rawBody, $signatureHeader)) {
            return WebhookOutcome::InvalidSignature;
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return WebhookOutcome::Malformed;
        }

        /** @var array<string, mixed> $payload */
        $eventId = (string) ($payload['id'] ?? '');
        $type = (string) ($payload['type'] ?? '');

        if ($eventId === '' || $type === '') {
            return WebhookOutcome::Malformed;
        }

        if ($type === 'ping') {
            return WebhookOutcome::Acknowledged;
        }

        $object = $payload['data']['object'] ?? null;

        if (! is_array($object) || ! isset($object['id'])) {
            return WebhookOutcome::Malformed;
        }

        /** @var array<string, mixed> $object */
        $media = Media::query()->where('provider_id', $object['id'])->first();

        if (! $media instanceof Media) {
            Log::info('Webhook arrived before the generation was recorded.', [
                'provider_id' => $object['id'],
                'event_id' => $eventId,
            ]);

            return WebhookOutcome::UnknownGeneration;
        }

        $event = WebhookEvent::record(self::PROVIDER, $eventId, $type, $media, $payload);

        if (! $event instanceof WebhookEvent) {
            return WebhookOutcome::Duplicate;
        }

        $this->applyResult->handle($media, ProviderGeneration::fromArray($object));

        $event->forceFill(['processed_at' => now()])->save();

        return WebhookOutcome::Applied;
    }
}
