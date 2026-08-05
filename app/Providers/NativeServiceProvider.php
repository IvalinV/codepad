<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Native\Mobile\Providers\ShareServiceProvider;
use Native\Mobile\UI\NativeUIServiceProvider as MobileUIServiceProvider;
use NativePHP\Clipboard\ClipboardServiceProvider;

class NativeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * The NativePHP plugins to enable.
     *
     * Only plugins listed here will be compiled into your native builds.
     * This is a security measure to prevent transitive dependencies from
     * automatically registering plugins without your explicit consent.
     *
     * @return array<int, class-string<ServiceProvider>>
     */
    public function plugins(): array
    {
        return [
            ClipboardServiceProvider::class,
            MobileUIServiceProvider::class,
            ShareServiceProvider::class,
        ];
    }
}
