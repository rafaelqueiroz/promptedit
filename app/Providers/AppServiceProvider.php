<?php

namespace App\Providers;

use App\Models\Media;
use App\Pricing\PricingCalculator;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\WebhookSignature;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PricingCalculator::class, function (): PricingCalculator {
            /** @var array{markup: string, rates: array<string, string>, rate_unit_tokens: int, estimate_tokens: array{characters_per_text_token: int, per_reference_image: int, output: array<string, array<string, int>>, reasoning: array<string, array{min: int, max: int}>}} $config */
            $config = config('pricing');

            return new PricingCalculator($config);
        });

        $this->app->singleton(PixelforgeClient::class, fn (): PixelforgeClient => new PixelforgeClient(
            $this->app->make(HttpFactory::class),
            (string) config('services.pixelforge.base_url'),
            config('services.pixelforge.key') === null ? null : (string) config('services.pixelforge.key'),
            (int) config('services.pixelforge.timeout'),
        ));

        $this->app->singleton(WebhookSignature::class, fn (): WebhookSignature => new WebhookSignature(
            (string) config('services.pixelforge.webhook_secret'),
            (int) config('pixelforge.webhook_tolerance_seconds'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Stable, readable morph keys: renaming or moving a class must not
        // orphan rows that already point at it.
        Relation::enforceMorphMap([
            'media' => Media::class,
        ]);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
