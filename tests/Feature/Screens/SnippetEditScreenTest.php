<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Models\Snippet;
use App\Native\SnippetEditScreen;
use App\Support\Highlighting\SnippetRenderer;
use App\Support\Preferences;
use Native\Mobile\System;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    System::rememberAppearance('light');
});

it('opens an existing snippet with its current values', function () {
    $snippet = Snippet::factory()->create([
        'title' => 'Retry helper',
        'body' => 'the stored body',
        'language' => Language::Go,
    ]);

    Native::test(SnippetEditScreen::class, ['snippet' => (string) $snippet->id])
        ->assertSet('title', 'Retry helper')
        ->assertSet('body', 'the stored body')
        ->assertSet('languageLabel', 'Go');
});

it('persists an edit and re-renders both themes', function () {
    $snippet = Snippet::factory()->create(['body' => 'before', 'language' => Language::Php]);
    app(SnippetRenderer::class)->refresh($snippet);

    Native::test(SnippetEditScreen::class, ['snippet' => (string) $snippet->id])
        ->set('body', 'after the edit')
        ->press('save')
        ->assertWentBack();

    $snippet->refresh();

    expect($snippet->body)->toBe('after the edit')
        ->and(app(SnippetRenderer::class)->renderFor($snippet, ThemeVariant::Light))->not->toBeNull()
        ->and(app(SnippetRenderer::class)->renderFor($snippet, ThemeVariant::Dark))->not->toBeNull();
});

it('does not re-render when only the title changed', function () {
    $snippet = Snippet::factory()->create(['title' => 'Before', 'body' => 'unchanged body']);
    app(SnippetRenderer::class)->refresh($snippet);

    $snippet->renders()->update(['content' => [[['text' => 'PLANTED', 'color' => '#abcdef']]]]);

    Native::test(SnippetEditScreen::class, ['snippet' => (string) $snippet->id])
        ->set('title', 'After')
        ->press('save');

    $snippet->refresh();

    expect($snippet->title)->toBe('After')
        ->and($snippet->renders()->first()->content)->toBe([[['text' => 'PLANTED', 'color' => '#abcdef']]]);
});

it('creates a new snippet and renders it', function () {
    Native::test(SnippetEditScreen::class)
        ->set('body', "<?php\necho 'new';")
        ->set('title', 'Fresh')
        ->select('changeLanguage', 'PHP')
        ->press('save')
        ->assertWentBack();

    $snippet = Snippet::query()->sole();

    expect($snippet->title)->toBe('Fresh')
        ->and($snippet->language)->toBe(Language::Php)
        ->and($snippet->renders()->count())->toBe(2);
});

it('defaults a new snippet to the last language used', function () {
    app(Preferences::class)->rememberLanguage(Language::Rust);

    Native::test(SnippetEditScreen::class)->assertSet('languageLabel', 'Rust');
});

it('remembers the language a snippet was saved with', function () {
    Native::test(SnippetEditScreen::class)
        ->set('body', 'fn main() {}')
        ->select('changeLanguage', 'Rust')
        ->press('save');

    expect(app(Preferences::class)->lastLanguage())->toBe(Language::Rust);
});

it('rejects an empty body with a readable message', function () {
    Native::test(SnippetEditScreen::class)
        ->set('body', '')
        ->press('save')
        ->assertSee('A snippet needs some code.')
        ->assertNoNavigation();

    expect(Snippet::query()->count())->toBe(0);
});

it('rejects a body over the 100 KB cap with the documented message', function () {
    Native::test(SnippetEditScreen::class)
        ->set('body', str_repeat('a', 102401))
        ->press('save')
        ->assertSee('Snippets are limited to 100 KB.')
        ->assertNoNavigation();

    expect(Snippet::query()->count())->toBe(0);
});

it('accepts a body at exactly the cap', function () {
    Native::test(SnippetEditScreen::class)
        ->set('body', str_repeat('a', 102400))
        ->press('save')
        ->assertWentBack();

    expect(Snippet::query()->count())->toBe(1);
});

it('rejects a title over the column length', function () {
    Native::test(SnippetEditScreen::class)
        ->set('body', 'fine')
        ->set('title', str_repeat('t', 256))
        ->press('save')
        ->assertNoNavigation();

    expect(Snippet::query()->count())->toBe(0);
});

it('clears a validation error once the problem is fixed', function () {
    Native::test(SnippetEditScreen::class)
        ->set('body', '')
        ->press('save')
        ->assertSee('A snippet needs some code.')
        ->set('body', 'now it has some')
        ->press('save')
        ->assertWentBack();

    expect(Snippet::query()->count())->toBe(1);
});

it('shows the derived title as the placeholder so the list holds no surprises', function () {
    Native::test(SnippetEditScreen::class)
        ->set('body', "  \nfirst real line\nsecond")
        ->assertSee('first real line');
});

it('saves an untitled snippet with a null title rather than an empty string', function () {
    Native::test(SnippetEditScreen::class)
        ->set('body', 'no title given')
        ->press('save');

    expect(Snippet::query()->sole()->title)->toBeNull();
});

it('discards an edit without touching the stored snippet', function () {
    $snippet = Snippet::factory()->create(['body' => 'the original']);

    Native::test(SnippetEditScreen::class, ['snippet' => (string) $snippet->id])
        ->set('body', 'an abandoned edit')
        ->press('cancel')
        ->assertWentBack();

    expect($snippet->fresh()->body)->toBe('the original');
});
