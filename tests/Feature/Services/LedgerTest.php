<?php

namespace Tests\Feature\Services;

use App\Models\CreditTransaction;
use App\Models\Media;
use App\Models\User;
use App\Pricing\Money;
use App\Services\Billing\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private function ledger(): Ledger
    {
        return $this->app->make(Ledger::class);
    }

    #[Test]
    public function a_charge_is_recorded_as_a_negative_amount_against_its_source(): void
    {
        $media = Media::factory()->create();

        $recorded = $this->ledger()->charge($media->user, Money::of('0.015981250'), $media, 'Image generation');

        $this->assertTrue($recorded);

        $row = CreditTransaction::query()->sole();

        $this->assertSame('-0.015981250', $row->amount);
        $this->assertSame('media', $row->source_type);
        $this->assertSame($media->id, $row->source_id);
        $this->assertSame('media:'.$media->id.':charge', $row->idempotency_key);
    }

    /**
     * The guarantee the whole billing story rests on: charging the same
     * source twice records once and reports that it did nothing.
     */
    #[Test]
    public function charging_the_same_source_twice_only_bills_once(): void
    {
        $media = Media::factory()->create();
        $price = Money::of('0.015981250');

        $first = $this->ledger()->charge($media->user, $price, $media, 'Image generation');
        $second = $this->ledger()->charge($media->user, $price, $media, 'Image generation');

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertSame(1, CreditTransaction::query()->count());
    }

    #[Test]
    public function a_zero_charge_is_not_recorded_at_all(): void
    {
        $media = Media::factory()->create();

        $this->assertFalse($this->ledger()->charge($media->user, Money::zero(), $media, 'Free'));
        $this->assertSame(0, CreditTransaction::query()->count());
    }

    #[Test]
    public function a_purchase_adds_credit(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->ledger()->purchase($user, Money::of('10'), 'topup:abc', 'Bought credits'));
        $this->assertFalse($this->ledger()->purchase($user, Money::of('10'), 'topup:abc', 'Bought credits'));

        $this->assertSame(1, CreditTransaction::query()->count());
        $this->assertSame('10.000000000', CreditTransaction::query()->sole()->amount);
    }

    /**
     * A balance is derived from the ledger, so it can never disagree with
     * its own history.
     */
    #[Test]
    public function the_balance_is_the_sum_of_the_ledger(): void
    {
        $media = Media::factory()->create();
        $user = $media->user;

        $this->assertTrue($this->ledger()->balanceFor($user)->isZero());

        $this->ledger()->purchase($user, Money::of('1'), 'topup:1', 'Bought credits');
        $this->ledger()->charge($user, Money::of('0.015981250'), $media, 'Image generation');

        $this->assertSame('0.984018750', (string) $this->ledger()->balanceFor($user));
    }

    #[Test]
    public function one_users_ledger_does_not_affect_another(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->ledger()->purchase($user, Money::of('5'), 'topup:1', 'Bought credits');

        $this->assertSame('5.000000000', (string) $this->ledger()->balanceFor($user));
        $this->assertTrue($this->ledger()->balanceFor($other)->isZero());
    }
}
