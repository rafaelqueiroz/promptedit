<?php

namespace App\Services\Pixelforge\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base for every Pixelforge API failure.
 *
 * The important distinction for callers is isRetryable(): a retryable failure
 * means the request definitely did not create a generation (so retrying cannot
 * double-charge), while a permanent one must fail the generation immediately.
 */
abstract class PixelforgeException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'unknown',
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    abstract public function isRetryable(): bool;

    /**
     * Seconds to wait before retrying, when the API told us.
     */
    public function retryAfter(): ?int
    {
        return null;
    }

    /**
     * Shown to the user on a failed generation.
     */
    public function userMessage(): string
    {
        return $this->getMessage();
    }
}
