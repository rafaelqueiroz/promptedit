<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Enums\MediaType;
use App\Pricing\Money;
use App\Services\Pixelforge\Data\TokenUsage;
use Carbon\CarbonImmutable;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

/**
 * One generated asset: a prompt, what it cost, and where the file lives.
 *
 * @property string $id
 * @property int $user_id
 * @property MediaType $type
 * @property GenerationStatus $status
 * @property string $prompt
 * @property string $size
 * @property string $quality
 * @property string|null $provider_id
 * @property string $estimated_provider_cost
 * @property string $estimated_price
 * @property string|null $provider_cost
 * @property string|null $price
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $source_url
 * @property CarbonImmutable|null $source_url_expires_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property array<string, mixed>|null $data
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Unguarded]
#[Table(name: 'media')]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'status' => GenerationStatus::class,
            'data' => 'array',
            'source_url_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return MorphMany<CreditTransaction, $this>
     */
    public function creditTransactions(): MorphMany
    {
        return $this->morphMany(CreditTransaction::class, 'source');
    }

    /**
     * Not yet settled, so still awaiting a webhook or a reconcile.
     *
     * @param  Builder<Media>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing]);
    }

    public function size(): ?ImageSize
    {
        return ImageSize::tryFrom($this->size);
    }

    public function quality(): ?ImageQuality
    {
        return ImageQuality::tryFrom($this->quality);
    }

    public function estimatedPriceMoney(): Money
    {
        return Money::of($this->estimated_price);
    }

    /**
     * What the user was actually charged, once Pixelforge reported usage.
     *
     * Named to avoid colliding with the `price` attribute: a method called
     * price() makes $this->price ambiguous, and on a freshly created model
     * the column is not in $attributes at all.
     */
    public function priceMoney(): ?Money
    {
        $price = $this->getAttribute('price');

        return $price === null ? null : Money::of((string) $price);
    }

    public function providerCostMoney(): ?Money
    {
        $cost = $this->getAttribute('provider_cost');

        return $cost === null ? null : Money::of((string) $cost);
    }

    /**
     * The token counts Pixelforge reported, if it has finished.
     */
    public function usage(): ?TokenUsage
    {
        $usage = $this->data['usage'] ?? null;

        return is_array($usage) ? TokenUsage::fromArray($usage) : null;
    }

    /**
     * Where the browser should load the image from.
     *
     * Our stored copy once it exists; otherwise Pixelforge's signed URL, which
     * expires 24 hours after completion.
     */
    public function imageUrl(): ?string
    {
        if ($this->path !== null) {
            return Storage::url($this->path);
        }

        return $this->source_url;
    }

    public function isStored(): bool
    {
        return $this->path !== null;
    }
}
