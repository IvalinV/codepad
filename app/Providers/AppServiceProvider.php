<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Phiki\Phiki;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Grammars whose Phiki-bundled copy contains regexes oniguruma refuses to
     * compile, replaced by patched copies in `resources/grammars`. Keyed by the
     * Phiki grammar name the patched file stands in for. See
     * `tests/Feature/Highlighting/PatchedGrammarTest.php`.
     *
     * @var list<string>
     */
    private const PATCHED_GRAMMARS = ['csharp', 'ruby'];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Phiki::class, function (): Phiki {
            $phiki = new Phiki;

            foreach (self::PATCHED_GRAMMARS as $name) {
                $phiki->environment()->grammars->register($name, resource_path("grammars/{$name}.json"));
            }

            return $phiki;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
