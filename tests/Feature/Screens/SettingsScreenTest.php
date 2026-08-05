<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Models\Snippet;
use App\Native\SettingsScreen;
use App\Support\Highlighting\SnippetRenderer;
use App\Support\Preferences;
use App\Support\SnippetArchive;
use Native\Mobile\System;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    System::rememberAppearance('light');
});

it('exports every snippet as valid JSON and hands it to the share sheet', function () {
    Snippet::factory()->create(['title' => 'One', 'language' => Language::Php]);
    Snippet::factory()->create(['title' => 'Two', 'language' => Language::Go]);

    $path = null;

    Native::test(SettingsScreen::class)
        ->press('export')
        ->assertNativeCalled('Share.File', function (array $params) use (&$path): bool {
            $path = $params['filePath'];

            return true;
        });

    expect($path)->toEndWith('.txt')
        ->and(is_file($path))->toBeTrue();

    $decoded = json_decode(file_get_contents($path), true);

    expect($decoded['version'])->toBe(1)
        ->and($decoded['snippets'])->toHaveCount(2)
        ->and(array_column($decoded['snippets'], 'title'))->toBe(['One', 'Two']);
});

it('exports an empty library without failing', function () {
    Native::test(SettingsScreen::class)
        ->press('export')
        ->assertSee('Exported 0 snippets');
});

it('fills the import box from the clipboard', function () {
    Native::fakeBridge()->withClipboard('{"version":1,"snippets":[]}');

    Native::test(SettingsScreen::class)
        ->press('pasteBackup')
        ->assertSet('backup', '{"version":1,"snippets":[]}');
});

it('imports snippets additively, never deleting what is already there', function () {
    $existing = Snippet::factory()->create(['title' => 'Already here']);

    $payload = json_encode([
        'version' => 1,
        'snippets' => [
            ['title' => 'Imported one', 'body' => 'body one', 'language' => 'php'],
            ['title' => 'Imported two', 'body' => 'body two', 'language' => 'go'],
        ],
    ]);

    Native::test(SettingsScreen::class)
        ->set('backup', $payload)
        ->press('import')
        ->assertSee('Imported 2 snippets');

    expect(Snippet::query()->count())->toBe(3)
        ->and($existing->fresh())->not->toBeNull();
});

it('gives imported snippets their renders, since they arrive without any', function () {
    $payload = json_encode([
        'version' => 1,
        'snippets' => [
            ['title' => 'Imported', 'body' => "<?php\necho 'hi';", 'language' => 'php'],
        ],
    ]);

    Native::test(SettingsScreen::class)
        ->set('backup', $payload)
        ->press('import');

    $imported = Snippet::query()->sole();

    expect($imported->renders()->count())->toBe(2)
        ->and(app(SnippetRenderer::class)->renderFor($imported, ThemeVariant::Light))->not->toBeNull()
        ->and(app(SnippetRenderer::class)->renderFor($imported, ThemeVariant::Dark))->not->toBeNull();
});

it('leaves the renders of existing snippets alone on import', function () {
    $existing = Snippet::factory()->create(['body' => 'untouched body']);
    app(SnippetRenderer::class)->refresh($existing);
    $existing->renders()->update(['content' => [[['text' => 'PLANTED', 'color' => '#abcdef']]]]);

    Native::test(SettingsScreen::class)
        ->set('backup', json_encode([
            'version' => 1,
            'snippets' => [['title' => 'New', 'body' => 'new body', 'language' => 'php']],
        ]))
        ->press('import');

    expect($existing->renders()->first()->content)->toBe([[['text' => 'PLANTED', 'color' => '#abcdef']]]);
});

it('surfaces a malformed backup as the human-readable message it already carries', function () {
    Native::test(SettingsScreen::class)
        ->set('backup', 'this is not a backup')
        ->press('import')
        ->assertSee('That does not look like a Codepad backup.');

    expect(Snippet::query()->count())->toBe(0);
});

it('surfaces an incompatible backup version', function () {
    Native::test(SettingsScreen::class)
        ->set('backup', json_encode(['version' => 99, 'snippets' => []]))
        ->press('import')
        ->assertSee('incompatible version');
});

it('says nothing was imported rather than claiming success on an empty backup', function () {
    Native::test(SettingsScreen::class)
        ->set('backup', json_encode(['version' => 1, 'snippets' => []]))
        ->press('import')
        ->assertSee('Imported 0 snippets');
});

it('round-trips the whole library through export and import', function () {
    Snippet::factory()->create(['title' => 'Round trip', 'body' => "a\nb\tc", 'language' => Language::Yaml]);

    $exported = app(SnippetArchive::class)->export();

    Snippet::query()->delete();

    Native::test(SettingsScreen::class)
        ->set('backup', $exported)
        ->press('import');

    $restored = Snippet::query()->sole();

    expect($restored->title)->toBe('Round trip')
        ->and($restored->body)->toBe("a\nb\tc")
        ->and($restored->language)->toBe(Language::Yaml)
        ->and($restored->renders()->count())->toBe(2);
});

it('starts out following the system appearance', function () {
    Native::test(SettingsScreen::class)->assertSet('theme', 'system');
});

it('switches to a fixed theme', function () {
    Native::test(SettingsScreen::class)
        ->press("chooseTheme('dark')")
        ->assertSet('theme', 'dark');

    expect(app(Preferences::class)->themePreference())->toBe(ThemeVariant::Dark)
        ->and(app(Preferences::class)->activeTheme())->toBe(ThemeVariant::Dark);
});

it('goes back to following the system', function () {
    Native::test(SettingsScreen::class)
        ->press("chooseTheme('light')")
        ->press("chooseTheme('system')")
        ->assertSet('theme', 'system');

    expect(app(Preferences::class)->themePreference())->toBeNull();
});

it('switching theme is a read, not a recompute', function () {
    $snippet = Snippet::factory()->create(['body' => "<?php\necho 'hi';", 'language' => Language::Php]);
    app(SnippetRenderer::class)->refresh($snippet);
    $hashes = $snippet->renders()->orderBy('id')->pluck('hash')->all();

    Native::test(SettingsScreen::class)->press("chooseTheme('dark')");

    expect($snippet->fresh()->renders()->orderBy('id')->pluck('hash')->all())->toBe($hashes)
        ->and(app(SnippetRenderer::class)->renderFor($snippet->fresh(), ThemeVariant::Dark))->not->toBeNull();
});

it('tells the user that import can never delete anything', function () {
    Native::test(SettingsScreen::class)->assertSee('never deletes');
});
