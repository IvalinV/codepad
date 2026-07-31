<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Support\Highlighting\HighlightedCode;
use App\Support\Highlighting\Highlighter;
use Illuminate\Support\Facades\Log;
use Phiki\Grammar\Grammar;
use Phiki\Phiki;

it('highlights php into coloured runs', function () {
    $result = app(Highlighter::class)->highlight("<?php\necho 'hi';", Language::Php, ThemeVariant::Light);

    expect($result)->toBeInstanceOf(HighlightedCode::class)
        ->and($result->lineCount())->toBe(2);
});

it('produces different colours for the two themes', function () {
    $highlighter = app(Highlighter::class);
    $code = "<?php\necho 'hi';";

    $light = $highlighter->highlight($code, Language::Php, ThemeVariant::Light)->toArray();
    $dark = $highlighter->highlight($code, Language::Php, ThemeVariant::Dark)->toArray();

    expect($light)->not->toBe($dark);
});

it('gives unmatched tokens the dark theme base foreground, not black', function () {
    $runs = collect(app(Highlighter::class)->highlight("<?php\necho 'hi';", Language::Php, ThemeVariant::Dark)->toArray())
        ->flatten(1);

    $semicolon = $runs->firstWhere('text', ';');

    expect($semicolon)->not->toBeNull()
        ->and($semicolon['color'])->toBe('#e1e4e8')
        ->and($semicolon['color'])->not->toBe('#000000');
});

it('gives unmatched tokens the light theme base foreground', function () {
    $runs = collect(app(Highlighter::class)->highlight("<?php\necho 'hi';", Language::Php, ThemeVariant::Light)->toArray())
        ->flatten(1);

    expect($runs->firstWhere('text', ';')['color'])->toBe('#24292e');
});

it('handles an empty body without erroring', function () {
    expect(app(Highlighter::class)->highlight('', Language::PlainText, ThemeVariant::Light)->lineCount())
        ->toBeLessThanOrEqual(1);
});

it('highlights every language in the allowlist without erroring', function (Language $language) {
    expect(app(Highlighter::class)->highlight("hello\nworld", $language, ThemeVariant::Light))
        ->toBeInstanceOf(HighlightedCode::class);
})->with(Language::cases());

/*
| A production bundle is pruned down to the grammars and themes App\Enums
| declares (see GrammarBundleClosureTest). Prune one file too many and Phiki's
| repositories raise an ErrorException out of file_get_contents — synchronously,
| inside SnippetRenderer::refresh(), i.e. while the user is saving. Losing the
| snippet is a far worse outcome than losing the colours, so highlight() must
| degrade to plain text instead of throwing. Missing files are simulated by
| re-registering a name against a path that does not exist, the same mechanism
| AppServiceProvider uses for the patched grammars.
*/

/** The source text a HighlightedCode's runs reconstruct, joined the way Phiki split it. */
function reconstructedSource(HighlightedCode $highlighted): string
{
    return collect($highlighted->toArray())
        ->map(fn (array $runs): string => collect($runs)->pluck('text')->implode(''))
        ->implode("\n");
}

it('falls back to plain text when a grammar file is missing', function () {
    Log::spy();

    $code = "<?php\n\necho 'hi';\nreturn 1;";

    app(Phiki::class)->environment()->grammars->register(Grammar::Php->value, '/nonexistent/php.json');

    $result = app(Highlighter::class)->highlight($code, Language::Php, ThemeVariant::Dark);

    expect($result)->toBeInstanceOf(HighlightedCode::class)
        ->and($result->lineCount())->toBe(4)
        ->and(reconstructedSource($result))->toBe($code);

    Log::shouldHaveReceived('warning')->once();
});

it('falls back to plain text when a theme file is missing', function () {
    $code = "<?php\n\necho 'hi';\nreturn 1;";

    app(Phiki::class)->environment()->themes->register(ThemeVariant::Dark->phikiTheme()->value, '/nonexistent/theme.json');

    $result = app(Highlighter::class)->highlight($code, Language::Php, ThemeVariant::Dark);

    expect($result)->toBeInstanceOf(HighlightedCode::class)
        ->and($result->lineCount())->toBe(4)
        ->and(reconstructedSource($result))->toBe($code);
});

it('carries the theme base foreground into the fallback when only the grammar is missing', function () {
    app(Phiki::class)->environment()->grammars->register(Grammar::Php->value, '/nonexistent/php.json');

    $colors = collect(app(Highlighter::class)->highlight("echo 'hi';", Language::Php, ThemeVariant::Dark)->toArray())
        ->flatten(1)
        ->pluck('color');

    expect($colors->all())->toBe(['#e1e4e8']);
});

it('uses real highlighting rather than the fallback when nothing is missing', function () {
    $colors = collect(app(Highlighter::class)->highlight("<?php\necho 'hi';", Language::Php, ThemeVariant::Dark)->toArray())
        ->flatten(1)
        ->pluck('color')
        ->unique();

    expect($colors->count())->toBeGreaterThan(1);
});
