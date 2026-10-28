<?php

namespace App\Providers;

use App\Enums\ThemeVariant;
use App\Support\Highlighting\CodePalette;
use Illuminate\Support\ServiceProvider;
use Phiki\Phiki;
use Phiki\Theme\Theme;

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

            $palette = new CodePalette;

            foreach (ThemeVariant::cases() as $variant) {
                $phiki->theme($variant->syntaxTheme(), Theme::parse($palette->definition($variant)));
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
