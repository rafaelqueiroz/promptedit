<?php

namespace App\Services\Billing;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Pricing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The append-only record of everything that moves a user's credit.
 *
 * Writes are idempotent by key: the unique index on `idempotency_key` is the
 * real guarantee that a duplicate webhook, a retried job or a concurrent
 * worker cannot charge the same thing twice. Nothing here updates or deletes.
 */
class Ledger
{
    /**
     * Charges a user for something, exactly once.
     *
     * @param  Model  $source  what the money is for, e.g. a Media row
     * @return bool whether this call recorded the charge (false if already recorded)
     */
    public function charge(User $user, Money $amount, Model $source, string $description): bool
    {
        if ($amount->isZero()) {
            return false;
        }

        return $this->record(
            user: $user,
            type: CreditTransactionType::Charge,
            // Charges are negative: a balance is the sum of the column.
            amount: '-'.$amount,
            key: $this->keyFor($source, 'charge'),
            source: $source,
            description: $description,
        );
    }

    /**
     * Adds credit, exactly once per key.
     */
    public function purchase(User $user, Money $amount, string $key, string $description): bool
    {
        return $this->record(
            user: $user,
            type: CreditTransactionType::Purchase,
            amount: (string) $amount,
            key: $key,
            source: null,
            description: $description,
        );
    }

    /**
     * A user's balance: the ledger summed, never a stored figure that could
     * drift away from its own history.
     */
    public function balanceFor(User $user): Money
    {
        $total = CreditTransaction::query()
            ->where('user_id', $user->id)
            ->sum('amount');

        return Money::of((string) $total);
    }

    public function keyFor(Model $source, string $purpose): string
    {
        return $source->getMorphClass().':'.$source->getKey().':'.$purpose;
    }

    private function record(
        User $user,
        CreditTransactionType $type,
        string $amount,
        string $key,
        ?Model $source,
        string $description,
    ): bool {
        /*
         * Wrapped in its own transaction so the unique violation rolls back a
         * savepoint rather than the caller's transaction. On Postgres a failed
         * statement aborts the whole transaction block — every later query
         * fails with 25P02 — and settlement runs inside one, so catching the
         * exception alone would leave the caller with a dead transaction.
         */
        try {
            DB::transaction(function () use ($user, $type, $amount, $key, $source, $description): void {
                CreditTransaction::query()->create([
                    'user_id' => $user->id,
                    'source_type' => $source?->getMorphClass(),
                    'source_id' => $source?->getKey(),
                    'type' => $type,
                    'amount' => $amount,
                    'idempotency_key' => $key,
                    'description' => $description,
                ]);
            });
        } catch (QueryException $e) {
            // Already recorded under this key by an earlier call. Losing the
            // race is the correct outcome, not an error.
            if ($this->isUniqueViolation($e)) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505' || str_contains($e->getMessage(), 'Unique violation');
    }
}
