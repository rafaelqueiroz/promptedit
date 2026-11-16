<?php

namespace App\Services\Pixelforge\Data;

final readonly class UsageDay
{
    public function __construct(
        public ?string $date,
        public int $generations,
        public int $inputTextTokens,
        public int $inputImageTokens,
        public int $outputTokens,
        public int $reasoningTokens,
        /** Pixelforge's own cost figure, as an exact decimal string. */
        public string $costUsd,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            date: isset($payload['date']) ? (string) $payload['date'] : null,
            generations: (int) ($payload['generations'] ?? 0),
            inputTextTokens: (int) ($payload['input_text_tokens'] ?? 0),
            inputImageTokens: (int) ($payload['input_image_tokens'] ?? 0),
            outputTokens: (int) ($payload['output_tokens'] ?? 0),
            reasoningTokens: (int) ($payload['reasoning_tokens'] ?? 0),
            costUsd: (string) ($payload['cost_usd'] ?? '0'),
        );
    }
}
