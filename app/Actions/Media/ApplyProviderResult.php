<?php

namespace App\Actions\Media;

use App\Enums\GenerationStatus;
use App\Jobs\StoreMediaFile;
use App\Models\Media;
use App\Pricing\Money;
use App\Pricing\PricingCalculator;
use App\Services\Billing\Ledger;
use App\Services\Pixelforge\Data\ProviderGeneration;
use App\Services\Pixelforge\Data\TokenUsage;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Facades\DB;

/**
 * Applies a Pixelforge result to a Media row.
 *
 * Every path into the system funnels through here — webhooks and the polling
 * reconciler alike — so idempotency and money are enforced in exactly one
 * place. Safe to call repeatedly with the same result.
 */
class ApplyProviderResult
{
    public function __construct(
        private readonly PricingCalculator $pricing,
        private readonly Ledger $ledger,
    ) {}

    /**
     * @return bool whether this call changed anything
     */
    public function handle(Media $media, ProviderGeneration $result): bool
    {
        return DB::transaction(function () use ($media, $result): bool {
            // Lock the row: two duplicate webhooks can land on two workers at
            // the same moment, and only one may settle it.
            $fresh = Media::query()->whereKey($media->getKey())->lockForUpdate()->first();

            if (! $fresh instanceof Media) {
                return false;
            }

            // Terminal states are final, and a status that does not move the
            // lifecycle forward is a stale, out-of-order event.
            if (! $fresh->status->canTransitionTo($result->status)) {
                return false;
            }

            $fresh->status = $result->status;
            $fresh->data = $this->mergeData($fresh, $result);

            if ($result->status === GenerationStatus::Succeeded) {
                $this->settleSucceeded($fresh, $result);
            }

            if ($result->status === GenerationStatus::Failed) {
                $this->settleFailed($fresh, $result);
            }

            $fresh->save();

            // Only once the row is committed, so the job cannot read a state
            // that does not exist yet.
            if ($result->status === GenerationStatus::Succeeded) {
                DB::afterCommit(fn (): PendingDispatch => dispatch(new StoreMediaFile($fresh->id)));
            }

            return true;
        });
    }

    private function settleSucceeded(Media $media, ProviderGeneration $result): void
    {
        $usage = $result->usage;

        // Succeeded without usage should not happen; charging a guess would be
        // worse than charging nothing, so record zero and keep the payload.
        $providerCost = $usage instanceof TokenUsage
            ? $this->pricing->providerCostForUsage($usage)
            : Money::zero();

        $price = $this->pricing->userPrice($providerCost);

        $media->provider_cost = (string) $providerCost;
        $media->price = (string) $price;
        $media->source_url = $result->imageUrl;
        $media->source_url_expires_at = $result->imageUrlExpiresAt;
        $media->completed_at = $result->completedAt ?? now();

        // Idempotent by key, so a duplicate event that slipped past the
        // status check still cannot bill the user twice.
        $this->ledger->charge($media->user, $price, $media, 'Image generation');
    }

    private function settleFailed(Media $media, ProviderGeneration $result): void
    {
        // A failed generation is free. Explicit zeroes, not nulls, so the
        // history page can show "$0.00" rather than "unknown".
        $media->provider_cost = (string) Money::zero();
        $media->price = (string) Money::zero();
        $media->error_code = $result->error?->code;
        $media->error_message = $result->error?->message;
        $media->completed_at = $result->completedAt ?? now();
    }

    /**
     * Keeps the provider payload verbatim for auditing, without letting a
     * later event erase what an earlier one reported.
     *
     * @return array<string, mixed>
     */
    private function mergeData(Media $media, ProviderGeneration $result): array
    {
        return array_filter([
            ...($media->data ?? []),
            'usage' => $result->usage?->toArray(),
            'rates' => $result->rates,
            'reference_images' => $result->referenceImages,
            'image_url_expires_at' => $result->imageUrlExpiresAt?->toIso8601ZuluString(),
            'started_at' => $result->startedAt?->toIso8601ZuluString(),
            'completed_at' => $result->completedAt?->toIso8601ZuluString(),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
