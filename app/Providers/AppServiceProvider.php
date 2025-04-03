<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * Class AppServiceProvider.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        Sanctum::ignoreMigrations();
        $this->app->singleton(\App\Services\OAuthService::class, function ($app) {
            return new \App\Services\OAuthService();
        });
        
        $this->app->singleton(\App\Services\ApiService::class, function ($app) {
            return new \App\Services\ApiService(
                $app->make(\App\Services\OAuthService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();
    }
}
