<?php

namespace App\Services\Pixelforge\Data;

/**
 * The token counts Pixelforge reports for a finished generation.
 *
 * Reasoning tokens are only known once a generation completes, which is why
 * the price shown before submitting is an estimate.
 */
final readonly class TokenUsage
{
    public function __construct(
        public int $inputTextTokens,
        public int $inputImageTokens,
        public int $outputTokens,
        public int $reasoningTokens,
        public int $totalTokens,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            inputTextTokens: (int) ($payload['input_text_tokens'] ?? 0),
            inputImageTokens: (int) ($payload['input_image_tokens'] ?? 0),
            outputTokens: (int) ($payload['output_tokens'] ?? 0),
            reasoningTokens: (int) ($payload['reasoning_tokens'] ?? 0),
            totalTokens: (int) ($payload['total_tokens'] ?? 0),
        );
    }

    /**
     * Keyed by token type so it lines up with the rate table.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputTextTokens,
            'input_image_tokens' => $this->inputImageTokens,
            'output_tokens' => $this->outputTokens,
            'reasoning_tokens' => $this->reasoningTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }

    /**
     * The billable token types only; total_tokens is a sum, not a line item.
     *
     * @return array<string, int>
     */
    public function billable(): array
    {
        return [
            'input_text_tokens' => $this->inputTextTokens,
            'input_image_tokens' => $this->inputImageTokens,
            'output_tokens' => $this->outputTokens,
            'reasoning_tokens' => $this->reasoningTokens,
        ];
    }
}
