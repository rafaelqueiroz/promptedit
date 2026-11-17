<?php

namespace App\Console\Commands;

use App\Actions\Media\ApplyProviderResult;
use App\Models\Media;
use App\Services\Pixelforge\Exceptions\PixelforgeException;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The safety net for webhooks that never arrive.
 *
 * It polls Pixelforge and feeds the result through the same settlement path a
 * webhook uses, so there is no second, divergent way to record money.
 */
#[Description('Settle generations whose webhook never arrived by polling Pixelforge')]
#[Signature('generations:reconcile {--seconds= : Only touch generations submitted at least this long ago}')]
class ReconcileGenerations extends Command
{
    public function handle(PixelforgeClient $client, ApplyProviderResult $applyResult): int
    {
        /*
         * Without a webhook endpoint this poll is the only thing that settles
         * a generation, so the grace period is short: Pixelforge finishes most
         * in 5-30 seconds, and making a user wait minutes to see their own
         * image is worse than a few extra polls. With webhooks configured
         * they almost always win the race anyway.
         */
        $seconds = (int) ($this->option('seconds') ?? config('pixelforge.reconcile_after_seconds'));

        $cutoff = now()->subSeconds($seconds);

        $pending = Media::query()
            ->pending()
            ->whereNotNull('provider_id')
            ->where('submitted_at', '<=', $cutoff)
            ->limit(100)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Nothing to reconcile.');

            return self::SUCCESS;
        }

        $settled = 0;

        foreach ($pending as $media) {
            try {
                $result = $client->getGeneration((string) $media->provider_id);
            } catch (PixelforgeException $e) {
                $this->warn("Could not fetch {$media->provider_id}: {$e->getMessage()}");

                continue;
            }

            if ($applyResult->handle($media, $result)) {
                $settled++;
                $this->line("Settled {$media->getKey()} as {$result->status->value}.");
            }
        }

        $this->info("Reconciled {$settled} of {$pending->count()} pending generations.");

        return self::SUCCESS;
    }
}
