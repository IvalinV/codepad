<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Support\Highlighting\Highlighter;

/**
 * Share of characters rendered at the dark theme's base foreground, i.e. the
 * proportion of the snippet that received no syntax colour at all.
 */
function baseForegroundShare(string $code, Language $language): float
{
    $runs = collect(app(Highlighter::class)->highlight($code, $language, ThemeVariant::Dark)->toArray())->flatten(1);
    $total = $runs->sum(fn (array $run): int => mb_strlen($run['text']));

    if ($total === 0) {
        return 0.0;
    }

    return $runs->filter(fn (array $run): bool => $run['color'] === '#e1e4e8')
        ->sum(fn (array $run): int => mb_strlen($run['text'])) / $total;
}

/**
 * The six-line class the defect was measured on: base foreground share is
 * ~51.7% bare, and rises to ~81.4% / ~88.5% once wrapped in a preprocessor
 * block that never closes.
 */
function csharpClass(): string
{
    return "public class Counter\n{\n    private int _count = 0;\n\n    public void Increment() => _count++;\n}";
}

it('keeps highlighting csharp after a preprocessor block closes', function (): void {
    $code = "#region Fields\n".csharpClass()."\n#endregion";

    expect(baseForegroundShare($code, Language::CSharp))->toBeLessThan(0.7);
});

it('keeps highlighting csharp after a conditional compilation block closes', function (): void {
    $code = "#if DEBUG\n".csharpClass()."\n#endif";

    expect(baseForegroundShare($code, Language::CSharp))->toBeLessThan(0.7);
});

it('colours csharp await as a keyword', function (): void {
    $runs = collect(app(Highlighter::class)->highlight('async Task M() { await X(); }', Language::CSharp, ThemeVariant::Dark)->toArray())->flatten(1);

    expect($runs->firstWhere('text', 'await')['color'])->not->toBe('#e1e4e8');
});

/**
 * Both GitHub themes map `variable.other` to the editor foreground, so a
 * correctly scoped block parameter is *expected* to render plain. What the
 * broken look-behind changed was the delimiters: with `patterns[106]` failing
 * to compile, `|` fell through to the binary-or operator rule and rendered in
 * the keyword colour. Its absence is the observable proof the pattern compiles.
 */
it('stops mis-scoping ruby block parameter pipes as an operator', function (): void {
    $runs = collect(app(Highlighter::class)->highlight("[1].each do |item|\n  item\nend", Language::Ruby, ThemeVariant::Dark)->toArray())->flatten(1);

    expect($runs->firstWhere('text', '|')['color'])->not->toBe('#f97583');
});

it('compiles every pattern it reaches for the patched grammars', function (Language $language, string $code): void {
    $failures = [];

    set_error_handler(function (int $severity, string $message) use (&$failures): bool {
        if (str_contains($message, 'mbregex compile err')) {
            $failures[] = $message;
        }

        return true;
    });

    try {
        app(Highlighter::class)->highlight($code, $language, ThemeVariant::Dark);
    } finally {
        restore_error_handler();
    }

    expect($failures)->toBeEmpty();
})->with([
    'csharp preprocessor' => [Language::CSharp, "#region Fields\npublic class Counter\n{\n    private int _count = 0;\n}\n#endregion"],
    'csharp await' => [Language::CSharp, 'async Task M() { await X(); }'],
    'ruby block' => [Language::Ruby, "[1].each do |item|\n  item\nend"],
    'ruby brace block' => [Language::Ruby, '[1].each { |item| item }'],
]);

it('still reconstructs the source exactly for patched grammars', function (Language $language): void {
    $code = "#region A\nint a = 1;\n#endregion";
    $text = collect(app(Highlighter::class)->highlight($code, $language, ThemeVariant::Dark)->toArray())
        ->map(fn (array $line): string => collect($line)->pluck('text')->implode(''))
        ->implode("\n");

    expect($text)->toBe($code);
})->with([Language::CSharp, Language::Ruby]);
