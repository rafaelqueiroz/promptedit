<?php

namespace App\Services\Pixelforge\Data;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;

/**
 * A generation object as Pixelforge returns it, from either an API response
 * or a webhook's data.object. One shape, one parser, one code path.
 */
final readonly class ProviderGeneration
{
    /**
     * @param  list<string>  $referenceImages
     * @param  array<string, string>  $metadata
     * @param  array<string, string>  $rates
     */
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        public string $prompt,
        public string $size,
        public string $quality,
        public array $referenceImages,
        public array $metadata,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public ?TokenUsage $usage,
        public array $rates,
        public ?GenerationError $error,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var array<string, mixed>|null $usage */
        $usage = $payload['usage'] ?? null;

        /** @var array<string, mixed>|null $error */
        $error = $payload['error'] ?? null;

        /** @var list<string> $referenceImages */
        $referenceImages = array_values(array_map(strval(...), (array) ($payload['reference_images'] ?? [])));

        /** @var array<string, string> $metadata */
        $metadata = array_map(strval(...), (array) ($payload['metadata'] ?? []));

        /** @var array<string, string> $rates */
        $rates = array_map(strval(...), (array) ($payload['rates'] ?? []));

        return new self(
            id: (string) $payload['id'],
            status: GenerationStatus::from((string) $payload['status']),
            prompt: (string) ($payload['prompt'] ?? ''),
            size: (string) ($payload['size'] ?? ''),
            quality: (string) ($payload['quality'] ?? ''),
            referenceImages: $referenceImages,
            metadata: $metadata,
            imageUrl: isset($payload['image_url']) ? (string) $payload['image_url'] : null,
            imageUrlExpiresAt: self::date($payload['image_url_expires_at'] ?? null),
            usage: is_array($usage) ? TokenUsage::fromArray($usage) : null,
            rates: $rates,
            error: is_array($error) ? GenerationError::fromArray($error) : null,
            createdAt: self::date($payload['created_at'] ?? null) ?? CarbonImmutable::now(),
            startedAt: self::date($payload['started_at'] ?? null),
            completedAt: self::date($payload['completed_at'] ?? null),
        );
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
