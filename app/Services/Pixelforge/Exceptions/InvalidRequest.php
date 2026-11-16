<?php

namespace App\Services\Pixelforge\Exceptions;

use Throwable;

/**
 * 400/404/409/422: the request itself is wrong. Retrying would fail the same
 * way, so the generation is failed immediately (and costs the user nothing).
 */
final class InvalidRequest extends PixelforgeException
{
    public function __construct(
        string $message,
        string $errorCode,
        ?int $status,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode, $status, $previous);
    }

    public function isRetryable(): bool
    {
        return false;
    }

    public function userMessage(): string
    {
        return match ($this->errorCode) {
            'content_policy_violation' => 'This prompt was rejected by the content policy. You were not charged.',
            'validation_failed' => 'That request was not valid: '.$this->getMessage(),
            default => $this->getMessage(),
        };
    }
}
