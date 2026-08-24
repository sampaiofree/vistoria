<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(fn ($request): bool => Gate::check('viewHorizon', [$request->user()]));
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user): bool => $user !== null
            && $user->isSuperAdmin()
            && $user->isActive()
            && ! $user->must_change_password
            && $user->organization_id === null);
    }
}
