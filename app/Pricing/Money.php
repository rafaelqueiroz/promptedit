<?php

namespace App\Pricing;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An exact USD amount with 8 decimal places.
 *
 * Pixelforge's costs come in fractions of a cent, so amounts are kept as
 * decimal strings and computed with bcmath. Floats never touch money.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    /**
     * Pixelforge reports costs to 9 decimal places (GET /v1/usage returns e.g.
     * "0.012785000"), and a user price is a cost x 1.25, which can need a 9th
     * decimal of its own: one reasoning token costs 0.0000125 and bills at
     * 0.000015625. Nine places represents both exactly.
     */
    public const int SCALE = 9;

    /** The smallest representable amount, used to round up. */
    private const string UNIT = '0.000000001';

    /**
     * @param  numeric-string  $amount
     */
    private function __construct(public string $amount) {}

    public static function of(string|int $amount): self
    {
        $amount = (string) $amount;

        // is_numeric() alone would accept "1e-5" or " 1", which bcmath rejects.
        if (! is_numeric($amount) || preg_match('/^-?\d+(\.\d+)?$/', $amount) !== 1) {
            throw new InvalidArgumentException("Invalid money amount [{$amount}].");
        }

        return new self(bcadd($amount, '0', self::SCALE));
    }

    public static function zero(): self
    {
        return self::of('0');
    }

    public function add(Money $other): self
    {
        return new self(bcadd($this->amount, $other->amount, self::SCALE));
    }

    /**
     * Multiply by a whole quantity (e.g. tokens × rate). Exact: the result
     * never needs more decimals than the amount already has.
     */
    public function multiply(int $quantity): self
    {
        return new self(bcmul($this->amount, (string) $quantity, self::SCALE));
    }

    /**
     * Divide by a whole number, e.g. converting a per-1,000,000-token rate
     * into a per-token amount.
     *
     * Exact for the rates in use: the extra working precision means the
     * division is carried out well below the scale before being brought back,
     * so no value is silently truncated mid-calculation.
     */
    public function divide(int $divisor): self
    {
        if ($divisor === 0) {
            throw new InvalidArgumentException('Cannot divide money by zero.');
        }

        return new self(bcdiv($this->amount, (string) $divisor, self::SCALE));
    }

    /**
     * Apply a multiplier such as a markup, rounding UP to the scale so the
     * result is never below the exact value. Only defined for non-negative
     * amounts, which is all pricing ever needs.
     */
    public function markedUp(string $factor): self
    {
        if (! is_numeric($factor)) {
            throw new InvalidArgumentException("Invalid markup factor [{$factor}].");
        }

        if ($this->isNegative()) {
            throw new InvalidArgumentException('Cannot mark up a negative amount.');
        }

        $exact = bcmul($this->amount, $factor, self::SCALE + 4);
        $truncated = bcadd($exact, '0', self::SCALE);

        if (bccomp($exact, $truncated, self::SCALE + 4) === 0) {
            return new self($truncated);
        }

        return new self(bcadd($truncated, self::UNIT, self::SCALE));
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) < 0;
    }

    public function equals(Money $other): bool
    {
        return bccomp($this->amount, $other->amount, self::SCALE) === 0;
    }

    public function jsonSerialize(): string
    {
        return $this->amount;
    }

    public function __toString(): string
    {
        return $this->amount;
    }
}
