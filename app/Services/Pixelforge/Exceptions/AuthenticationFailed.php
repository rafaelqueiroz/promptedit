<?php

namespace App\Services\Pixelforge\Exceptions;

use Throwable;

/**
 * 401: our API key is missing, malformed or revoked. Not retryable, and an
 * operator problem rather than a user one.
 */
final class AuthenticationFailed extends PixelforgeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 'invalid_api_key', 401, $previous);
    }

    public function isRetryable(): bool
    {
        return false;
    }

    public function userMessage(): string
    {
        return 'Image generation is temporarily misconfigured. Please try again later.';
    }
}
