<?php

namespace App\Services\Pixelforge\Exceptions;

use Throwable;

/**
 * 429: request rate, concurrency, or daily cap. The request was rejected
 * before a generation was created, so retrying is safe.
 */
final class RateLimited extends PixelforgeException
{
    public function __construct(
        string $message,
        string $errorCode,
        private readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode, 429, $previous);
    }

    public function isRetryable(): bool
    {
        return true;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function userMessage(): string
    {
        return match ($this->errorCode) {
            'concurrency_limit_exceeded' => 'Too many generations running at once. This one will start shortly.',
            'daily_limit_exceeded' => 'The daily generation limit has been reached. Please try again tomorrow.',
            default => 'Pixelforge is rate limiting us. This generation will be retried automatically.',
        };
    }
}
