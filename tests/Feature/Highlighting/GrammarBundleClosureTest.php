<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Collection;
use Phiki\Grammar\Grammar;

/**
 * Regression guard for Task 13's bundle pruning.
 *
 * `config('nativephp.cleanup_exclude_files')` hand-lists which Phiki grammar
 * files get deleted from the production bundle. That list was derived once,
 * mechanically, from `App\Enums\Language::cases()` plus the transitive
 * `include` closure — but nothing re-derives or checks it afterwards. Adding
 * a 17th `Language` case compiles, passes `LanguageTest`, and passes
 * `HighlighterTest`'s `Language::cases()` dataset, because the dev tree is
 * unpruned. The grammar file only turns out to be missing the first time a
 * production build tries to highlight that language on a device — a runtime
 * crash (`ErrorException` from `GrammarRepository::get()`'s
 * `file_get_contents()` on a deleted file), not a build error.
 *
 * This test re-derives the same closure on every run and asserts none of it
 * appears in the exclusion list, so a newly added language that isn't yet
 * covered by the closure fails loudly, here, instead of on a user's phone.
 *
 * The same pruning covers 58 theme files, and `ThemeVariant` is exposed to
 * exactly the same staleness: adding a third variant whose Phiki theme is on
 * the exclusion list fails only two incidental hard-coded counts, both of
 * which invite being bumped. The second test below closes that half. Themes
 * need no closure walk — no theme file declares an `include` and
 * `Phiki\Theme\Theme` has no include mechanism — so the required set is flat.
 */

/**
 * Scope name => grammar value, keyed with the same last-registration-wins
 * semantics `Phiki\Grammar\GrammarRepository::__construct()` uses when it
 * loops over `Grammar::cases()` and assigns `$scopesToGrammar[$scopeName]`.
 *
 * @return array<string, string>
 */
function grammarScopeToValueMap(): array
{
    $map = [];

    foreach (Grammar::cases() as $grammar) {
        $map[$grammar->scopeName()] = $grammar->value;
    }

    return $map;
}

/**
 * Every external scope name a grammar's `include` directives reference,
 * excluding local (`#name`), `$self`, and `$base` references, which never
 * resolve to another file.
 *
 * @param  array<mixed>  $node
 * @return list<string>
 */
function externalIncludeScopes(array $node): array
{
    $found = [];
    $stack = [$node];

    while ($stack !== []) {
        $current = array_pop($stack);

        if (! is_array($current)) {
            continue;
        }

        if (isset($current['include']) && is_string($current['include'])) {
            $include = $current['include'];

            if ($include !== '$self' && $include !== '$base' && ! str_starts_with($include, '#')) {
                $found[] = str_contains($include, '#') ? explode('#', $include, 2)[0] : $include;
            }
        }

        foreach ($current as $value) {
            if (is_array($value)) {
                $stack[] = $value;
            }
        }
    }

    return $found;
}

/**
 * The file a grammar value actually loads from at runtime. `AppServiceProvider`
 * registers the two patched grammars over the vendor originals (`Phiki::class`
 * singleton, `PATCHED_GRAMMARS`), so those two must resolve to
 * `resources/grammars/`, not `vendor/phiki/phiki/resources/grammars/`.
 *
 * @param  list<string>  $patchedGrammars
 */
function grammarRuntimePath(string $value, array $patchedGrammars): string
{
    if (in_array($value, $patchedGrammars, true)) {
        return resource_path("grammars/{$value}.json");
    }

    return base_path("vendor/phiki/phiki/resources/grammars/{$value}.json");
}

/**
 * BFS the `include` graph from the seed grammars to a fixed point.
 *
 * @param  array<string, string>  $seedLanguageByGrammar  grammar value => Language case name that seeded it
 * @param  array<string, string>  $scopeToValue
 * @param  list<string>  $patchedGrammars
 * @return array<string, ?string> grammar value => the grammar value that pulled it in (null for a seed)
 */
function grammarIncludeClosure(array $seedLanguageByGrammar, array $scopeToValue, array $patchedGrammars): array
{
    $reachedVia = [];
    $queue = [];

    foreach (array_keys($seedLanguageByGrammar) as $value) {
        $reachedVia[$value] = null;
        $queue[] = $value;
    }

    while ($queue !== []) {
        $value = array_shift($queue);
        $path = grammarRuntimePath($value, $patchedGrammars);

        if (! is_file($path)) {
            continue;
        }

        $json = json_decode(file_get_contents($path), true);

        if (! is_array($json)) {
            continue;
        }

        foreach (array_unique(externalIncludeScopes($json)) as $scope) {
            // Roughly two dozen `include` targets across the closure (e.g.
            // `source.scss`, `source.arm`, `text.html.javadoc`) have no
            // matching `Grammar` case at all. Those already fail gracefully
            // via `UnrecognisedGrammarException` at runtime regardless of
            // pruning, so they're not part of the risk this test guards.
            if (! isset($scopeToValue[$scope])) {
                continue;
            }

            $target = $scopeToValue[$scope];

            if (! array_key_exists($target, $reachedVia)) {
                $reachedVia[$target] = $value;
                $queue[] = $target;
            }
        }
    }

    return $reachedVia;
}

/**
 * The Phiki resource files under `vendor/phiki/phiki/resources/{$subdirectory}/`
 * that production bundling deletes, keyed by basename without the extension.
 *
 * @return Collection<string, int>
 */
function excludedPhikiResources(string $subdirectory): Collection
{
    return collect(config('nativephp.cleanup_exclude_files'))
        ->filter(fn (string $pattern): bool => str_starts_with($pattern, "vendor/phiki/phiki/resources/{$subdirectory}/"))
        ->map(fn (string $pattern): string => basename($pattern, '.json'))
        ->flip();
}

/** Renders "Language::Php -> html -> ... -> kotlin" for a failure message. */
function describeClosurePath(string $value, array $reachedVia, array $seedLanguageByGrammar): string
{
    $chain = [$value];
    $current = $value;

    while ($reachedVia[$current] !== null) {
        $current = $reachedVia[$current];
        $chain[] = $current;
    }

    $chain = array_reverse($chain);
    $language = $seedLanguageByGrammar[$chain[0]] ?? '?';

    return "Language::{$language} -> ".implode(' -> ', $chain);
}

it('keeps every grammar reachable from Language::cases() off the bundle exclusion list', function (): void {
    $patchedGrammars = (new ReflectionClass(AppServiceProvider::class))->getConstant('PATCHED_GRAMMARS');

    $seedLanguageByGrammar = [];
    foreach (Language::cases() as $case) {
        $seedLanguageByGrammar[$case->grammar()->value] ??= $case->name;
    }

    $reachedVia = grammarIncludeClosure($seedLanguageByGrammar, grammarScopeToValueMap(), $patchedGrammars);

    $excludedGrammars = excludedPhikiResources('grammars');

    $requiredButExcluded = [];

    foreach (array_keys($reachedVia) as $value) {
        // Unreached today: neither patched grammar's vendor original is on the
        // exclusion list (both are kept, so VendoredGrammarDriftTest can
        // re-derive the patched copies from them), so the check below would
        // pass for them anyway. Kept as a guard rather than removed — the
        // runtime loads these two from resources/, not vendor/, so if their
        // vendor originals ever were pruned that would not be the runtime
        // hazard this test exists to catch.
        if (in_array($value, $patchedGrammars, true)) {
            continue;
        }

        if ($excludedGrammars->has($value)) {
            $requiredButExcluded[] = sprintf(
                '  - %s.json (%s)',
                $value,
                describeClosurePath($value, $reachedVia, $seedLanguageByGrammar)
            );
        }
    }

    expect($requiredButExcluded)->toBe([], sprintf(
        "The following grammar(s) are required by App\\Enums\\Language — directly, or transitively via an\n".
        "`include` another required grammar declares — but are listed in config('nativephp.cleanup_exclude_files'):\n\n".
        "%s\n\n".
        "Deleting a grammar Phiki still references during production bundling is a RUNTIME error, not a build\n".
        "error: the app builds and every existing test passes (the dev tree is unpruned), then highlighting\n".
        "that language throws an uncaught ErrorException the first time a user opens it on a device.\n\n".
        "Fix by removing the matching 'vendor/phiki/phiki/resources/grammars/<name>.json' line(s) from\n".
        'cleanup_exclude_files in config/nativephp.php.',
        implode("\n", $requiredButExcluded)
    ));
});

it('keeps every theme required by ThemeVariant::cases() off the bundle exclusion list', function (): void {
    $excludedThemes = excludedPhikiResources('themes');

    $requiredButExcluded = [];

    foreach (ThemeVariant::cases() as $case) {
        $value = $case->phikiTheme()->value;

        if ($excludedThemes->has($value)) {
            $requiredButExcluded[] = sprintf('  - %s.json (ThemeVariant::%s)', $value, $case->name);
        }
    }

    expect($requiredButExcluded)->toBe([], sprintf(
        "The following theme(s) are required by App\\Enums\\ThemeVariant but are listed in\n".
        "config('nativephp.cleanup_exclude_files'):\n\n".
        "%s\n\n".
        "Deleting a theme Phiki still references during production bundling is a RUNTIME error, not a build\n".
        "error: the app builds and every existing test passes (the dev tree is unpruned), then rendering a\n".
        "snippet in that theme fails the first time a user selects it on a device.\n\n".
        "Fix by removing the matching 'vendor/phiki/phiki/resources/themes/<name>.json' line(s) from\n".
        'cleanup_exclude_files in config/nativephp.php.',
        implode("\n", $requiredButExcluded)
    ));
});
