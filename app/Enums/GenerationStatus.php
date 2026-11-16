<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * Terminal states are never left, so a late webhook cannot undo them.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed], true);
    }

    /**
     * Where this status sits in the lifecycle. Used to drop stale events:
     * Pixelforge delivers webhooks in any order, so a `processing` event
     * arriving after `succeeded` must not move the generation backwards.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Queued => 0,
            self::Processing => 1,
            self::Succeeded, self::Failed => 2,
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return ! $this->isTerminal() && $next->rank() > $this->rank();
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
