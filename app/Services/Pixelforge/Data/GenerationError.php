<?php

namespace App\Services\Pixelforge\Data;

final readonly class GenerationError
{
    public function __construct(
        public string $code,
        public string $message,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            code: (string) ($payload['code'] ?? 'unknown'),
            message: (string) ($payload['message'] ?? 'The generation failed.'),
        );
    }
}
