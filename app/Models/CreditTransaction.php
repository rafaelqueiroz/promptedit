<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use App\Pricing\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An append-only ledger entry. Balances are the sum of these rows, so nothing
 * is ever updated or deleted here.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $source_type
 * @property string|null $source_id
 * @property CreditTransactionType $type
 * @property string $amount
 * @property string $idempotency_key
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 */
#[Unguarded]
class CreditTransaction extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whatever moved the money: a generation today, a payment later.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function amount(): Money
    {
        return Money::of($this->amount);
    }
}
