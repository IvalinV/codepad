<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Support\Highlighting\HighlightedCode;
use App\Support\Highlighting\Highlighter;

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
