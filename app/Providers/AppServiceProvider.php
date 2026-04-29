<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
