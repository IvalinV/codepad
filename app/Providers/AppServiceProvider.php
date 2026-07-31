<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Phiki\Phiki;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Phiki::class, fn (): Phiki => new Phiki);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
