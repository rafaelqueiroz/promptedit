<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * Locally Horizon is open; anywhere else it is admins only.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user): bool => (bool) $user?->is_admin);
    }
}
