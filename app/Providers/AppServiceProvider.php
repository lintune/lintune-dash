<?php

namespace App\Providers;

use App\Auth\TenantSessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Auth::extend('tenant_session', function ($app, $name, array $config) {
            return new TenantSessionGuard();
        });

        try {
            $url = \App\Models\Setting::get('keycloak.url');
            if ($url) {
                config(['keycloak.base_url' => $url]);
            }
        } catch (\Throwable) {
            // DB not yet migrated — fall back to config.
        }
    }
}
