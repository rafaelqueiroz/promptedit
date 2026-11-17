<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A received webhook delivery.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $type
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $processed_at
 */
#[Unguarded]
class WebhookEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Claims an event for processing, or returns null if it is already done.
     *
     * The insert-or-ignore leans on the (provider, event_id) unique index so
     * two concurrent deliveries of the same event cannot both be recorded —
     * the database decides, not a check-then-insert race that can lose.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function record(
        string $provider,
        string $eventId,
        string $type,
        Model $subject,
        array $payload,
    ): ?self {
        self::query()->fillAndInsertOrIgnore([[
            'provider' => $provider,
            'event_id' => $eventId,
            'type' => $type,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'payload' => $payload,
        ]]);

        $event = self::query()
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->first();

        // Present and already applied: a duplicate delivery.
        return $event?->processed_at === null ? $event : null;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
