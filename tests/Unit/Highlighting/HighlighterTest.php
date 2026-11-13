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

it('highlights markdown into themed coloured runs', function () {
    $markdown = "# Heading\n\n**important** [link](https://example.com)\n\n- item";

    $light = app(Highlighter::class)->highlight($markdown, Language::Markdown, ThemeVariant::Light);
    $dark = app(Highlighter::class)->highlight($markdown, Language::Markdown, ThemeVariant::Dark);

    expect($light)->toBeInstanceOf(HighlightedCode::class)
        ->and($light->lineCount())->toBe(5)
        ->and(collect($light->toArray())->flatten(1)->pluck('color')->unique())->toHaveCount(3)
        ->and(collect($dark->toArray())->flatten(1)->pluck('color')->unique())->toHaveCount(3)
        ->and($light->toArray())->not->toBe($dark->toArray());
});

it('produces different colours for the two themes', function () {
    $highlighter = app(Highlighter::class);
    $code = "<?php\necho 'hi';";

    $light = $highlighter->highlight($code, Language::Php, ThemeVariant::Light)->toArray();
    $dark = $highlighter->highlight($code, Language::Php, ThemeVariant::Dark)->toArray();

    expect($light)->not->toBe($dark);
});

it('uses Codepad appearance colors for syntax tokens', function () {
    $highlighter = app(Highlighter::class);
    $code = "<?php\npublic function test(): void\n{\n    echo 'hi';\n}\n// note";

    $light = collect($highlighter->highlight($code, Language::Php, ThemeVariant::Light)->toArray())
        ->flatten(1);
    $dark = collect($highlighter->highlight($code, Language::Php, ThemeVariant::Dark)->toArray())
        ->flatten(1);

    expect($light->firstWhere('text', 'public')['color'])->toBe('#C2185B')
        ->and($light->firstWhere('text', 'function')['color'])->toBe('#C2185B')
        ->and($light->firstWhere('text', 'test')['color'])->toBe('#7C3AED')
        ->and($light->firstWhere('text', 'hi')['color'])->toBe('#1D4ED8')
        ->and($light->firstWhere('text', '//')['color'])->toBe('#5B6172')
        ->and($dark->firstWhere('text', 'public')['color'])->toBe('#F472B6')
        ->and($dark->firstWhere('text', 'function')['color'])->toBe('#F472B6')
        ->and($dark->firstWhere('text', 'test')['color'])->toBe('#A78BFA')
        ->and($dark->firstWhere('text', 'hi')['color'])->toBe('#93C5FD')
        ->and($dark->firstWhere('text', '//')['color'])->toBe('#A6ACC0');
});

it('keeps representative syntax colored across every language', function (Language $language, string $code) {
    $base = '#1E2330';
    $colours = collect(app(Highlighter::class)->highlight($code, $language, ThemeVariant::Light)->toArray())
        ->flatten(1)
        ->pluck('color');

    expect($colours->unique()->diff([$base]))->not->toBeEmpty();
})->with([
    'markdown' => [Language::Markdown, '# Heading **bold**'],
    'php' => [Language::Php, '<?php echo $value;'],
    'javascript' => [Language::JavaScript, 'const value = true;'],
    'typescript' => [Language::TypeScript, "const value: string = 'x';"],
    'python' => [Language::Python, 'def hello(): return True'],
    'go' => [Language::Go, 'func main() { return }'],
    'rust' => [Language::Rust, 'fn main() { let value = true; }'],
    'java' => [Language::Java, 'class Demo {}'],
    'csharp' => [Language::CSharp, 'class Demo { public bool Ready = true; }'],
    'ruby' => [Language::Ruby, "def hello; puts 'hi'; end"],
    'sql' => [Language::Sql, 'SELECT * FROM users WHERE id = 1;'],
    'bash' => [Language::Bash, 'echo "hi"'],
    'json' => [Language::Json, '{"enabled": true}'],
    'yaml' => [Language::Yaml, 'enabled: true'],
    'html' => [Language::Html, '<div class="x">Hi</div>'],
    'css' => [Language::Css, '.box { color: red; }'],
]);

it('uses dedicated colors for SQL keywords, numbers, and strings', function () {
    $sql = collect(app(Highlighter::class)->highlight('SELECT * FROM users WHERE id = 1;', Language::Sql, ThemeVariant::Light)->toArray())
        ->flatten(1);

    expect($sql->firstWhere('text', 'SELECT')['color'])->toBe('#C2185B')
        ->and($sql->firstWhere('text', '1')['color'])->toBe('#BE185D');
});

it('uses dedicated colors for CSS properties and values', function () {
    $runs = collect(app(Highlighter::class)->highlight('.box { color: red; }', Language::Css, ThemeVariant::Light)->toArray())
        ->flatten(1);

    expect($runs->firstWhere('text', 'color')['color'])->toBe('#7C3AED')
        ->and($runs->firstWhere('text', 'red')['color'])->toBe('#DB2777');
});

it('gives unmatched tokens the dark theme base foreground, not black', function () {
    $runs = collect(app(Highlighter::class)->highlight("<?php\necho 'hi';", Language::Php, ThemeVariant::Dark)->toArray())
        ->flatten(1);

    $semicolon = $runs->firstWhere('text', ';');

    expect($semicolon)->not->toBeNull()
        ->and($semicolon['color'])->toBe('#F3F4F8')
        ->and($semicolon['color'])->not->toBe('#000000');
});

it('gives unmatched tokens the light theme base foreground', function () {
    $runs = collect(app(Highlighter::class)->highlight("<?php\necho 'hi';", Language::Php, ThemeVariant::Light)->toArray())
        ->flatten(1);

    expect($runs->firstWhere('text', ';')['color'])->toBe('#1E2330');
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

    expect($colors->all())->toBe(['#F3F4F8']);
});

it('falls back to plain text for a body that is not valid UTF-8', function () {
    $code = "caf\xE9\nbar";

    $result = app(Highlighter::class)->highlight($code, Language::Php, ThemeVariant::Dark);

    expect($result)->toBeInstanceOf(HighlightedCode::class)
        ->and($result->lineCount())->toBe(2)
        ->and(reconstructedSource($result))->toBe(mb_scrub($code, 'UTF-8'))
        ->and(mb_check_encoding(reconstructedSource($result), 'UTF-8'))->toBeTrue();
});

it('uses real highlighting rather than the fallback when nothing is missing', function () {
    $colors = collect(app(Highlighter::class)->highlight("<?php\necho 'hi';", Language::Php, ThemeVariant::Dark)->toArray())
        ->flatten(1)
        ->pluck('color')
        ->unique();

    expect($colors->count())->toBeGreaterThan(1);
});
