<?php

namespace App\Services\Media;

/**
 * What the processor decided, so the controller can map it to a status code
 * without knowing why.
 */
enum WebhookOutcome: string
{
    case Applied = 'applied';

    /** A test ping from the dashboard. */
    case Acknowledged = 'acknowledged';

    /** A redelivery of an event already applied. */
    case Duplicate = 'duplicate';

    case InvalidSignature = 'invalid_signature';

    case Malformed = 'malformed';

    /**
     * The webhook outran our own submission request. Asking for a redelivery
     * is the cheapest fix; the reconciler would catch it regardless.
     */
    case UnknownGeneration = 'unknown_generation';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Applied, self::Acknowledged, self::Duplicate => 200,
            self::InvalidSignature => 401,
            self::Malformed => 422,
            self::UnknownGeneration => 500,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::Applied => 'ok',
            self::Acknowledged => 'pong',
            self::Duplicate => 'Already processed.',
            self::InvalidSignature => 'Invalid signature.',
            self::Malformed => 'Malformed event.',
            self::UnknownGeneration => 'Unknown generation.',
        };
    }
}
