<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind resolvable tenant values into the container.
        // Defaults: null (no scope). ResolveTenant middleware overwrites these per request.
        $this->app->bind('church.id', fn () => null);
        $this->app->bind('church',    fn () => null);
    }
}
