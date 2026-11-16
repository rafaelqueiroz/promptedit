<?php

namespace App\Services\Pixelforge\Exceptions;

use Throwable;

/**
 * 503 overloaded, or a 5xx/connection failure. Retryable.
 */
final class Overloaded extends PixelforgeException
{
    public function __construct(
        string $message,
        string $errorCode = 'overloaded',
        ?int $status = 503,
        private readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode, $status, $previous);
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
        return 'Pixelforge is temporarily unavailable. This generation will be retried automatically.';
    }
}
