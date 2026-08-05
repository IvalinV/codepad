<?php

use App\Enums\Language;
use App\Models\Snippet;
use App\Native\SnippetEditScreen;
use App\Native\SnippetListScreen;
use App\Support\Preferences;
use Native\Mobile\System;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    System::rememberAppearance('light');
});

it('pre-fills a new snippet from the clipboard', function () {
    Native::fakeBridge()->withClipboard('SELECT * FROM snippets;');

    Native::test(SnippetEditScreen::class)
        ->assertSet('body', 'SELECT * FROM snippets;')
        ->assertSee('SELECT * FROM snippets;');
});

it('opens an empty editor when the clipboard is empty, without complaint', function () {
    Native::fakeBridge()->withClipboard('');

    Native::test(SnippetEditScreen::class)
        ->assertSet('body', '')
        ->assertDontSee('clipboard is empty')
        ->assertDontSee('error');
});

it('opens an empty editor when the clipboard holds nothing at all', function () {
    Native::test(SnippetEditScreen::class)->assertSet('body', '');
});

it('preselects the last-used language on a captured snippet', function () {
    app(Preferences::class)->rememberLanguage(Language::Sql);
    Native::fakeBridge()->withClipboard('SELECT 1;');

    Native::test(SnippetEditScreen::class)->assertSet('languageLabel', 'SQL');
});

it('never overwrites an existing snippet with the clipboard', function () {
    $snippet = Snippet::factory()->create(['body' => 'the stored body']);
    Native::fakeBridge()->withClipboard('something unrelated on the clipboard');

    Native::test(SnippetEditScreen::class, ['snippet' => (string) $snippet->id])
        ->assertSet('body', 'the stored body');
});

it('reaches the capture screen from the list', function () {
    Native::test(SnippetListScreen::class)
        ->press('create')
        ->assertNavigatedTo('/snippets/new');
});

it('captures and saves in one pass', function () {
    Native::fakeBridge()->withClipboard('func main() {}');

    Native::test(SnippetEditScreen::class)
        ->select('changeLanguage', 'Go')
        ->press('save')
        ->assertReplacedWith('/');

    $snippet = Snippet::query()->sole();

    expect($snippet->body)->toBe('func main() {}')
        ->and($snippet->language)->toBe(Language::Go)
        ->and($snippet->displayTitle())->toBe('func main() {}');
});

it('does not read the clipboard again once the user has started typing', function () {
    Native::fakeBridge()->withClipboard('the first clipboard value');

    $screen = Native::test(SnippetEditScreen::class)
        ->assertSet('body', 'the first clipboard value')
        ->set('body', 'what the user typed instead');

    Native::fakeBridge()->withClipboard('a different clipboard value');

    $screen->press('save');

    expect(Snippet::query()->sole()->body)->toBe('what the user typed instead');
});
