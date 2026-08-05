<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Models\Snippet;
use App\Models\SnippetRender;
use App\Native\SnippetShowScreen;
use App\Support\Highlighting\SnippetRenderer;
use Native\Mobile\System;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    System::rememberAppearance('light');
});

/** Every colour appearing on a `color` prop anywhere in the rendered tree. */
function renderedColours(array $tree): array
{
    $colours = [];

    array_walk_recursive($tree, function ($value, $key) use (&$colours): void {
        if ($key === 'color' && is_string($value)) {
            $colours[] = strtolower($value);
        }
    });

    return $colours;
}

it('renders the cached highlighted runs', function () {
    $snippet = Snippet::factory()->create(['body' => "<?php\necho 'hi';", 'language' => Language::Php]);
    app(SnippetRenderer::class)->refresh($snippet);

    $colours = renderedColours(
        Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
            ->assertSee('echo')
            ->tree()
    );

    expect(array_unique($colours))->toHaveCount(count(array_unique($colours)))
        ->and(count(array_unique($colours)))->toBeGreaterThan(1);
});

it('falls back to the raw body when no render has been made yet', function () {
    $snippet = Snippet::factory()->create(['body' => 'distinctive body line', 'language' => Language::Php]);

    expect($snippet->renders()->count())->toBe(0);

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->assertSee('distinctive body line')
        ->assertDontSee('could not be highlighted');
});

it('falls back to the raw body when the stored render is stale', function () {
    $snippet = Snippet::factory()->create(['body' => 'original body', 'language' => Language::Php]);
    app(SnippetRenderer::class)->refresh($snippet);

    $snippet->update(['body' => 'edited body that was never re-rendered']);

    expect(app(SnippetRenderer::class)->renderFor($snippet->fresh(), ThemeVariant::Light))->toBeNull();

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->assertSee('edited body that was never re-rendered')
        ->assertDontSee('original body');
});

it('truncates a long snippet and offers to show the rest', function () {
    $snippet = Snippet::factory()->create([
        'body' => implode("\n", array_map(fn (int $n): string => "line {$n}", range(1, 400))),
        'language' => Language::PlainText,
    ]);
    app(SnippetRenderer::class)->refresh($snippet);

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->assertSee('line 300')
        ->assertDontSee('line 301')
        ->assertSee('Show all 400 lines')
        ->press('showEverything')
        ->assertSet('showAllLines', true)
        ->assertSee('line 400')
        ->assertDontSee('Show all 400 lines');
});

it('does not offer to show everything when the snippet already fits', function () {
    $snippet = Snippet::factory()->create(['body' => "one\ntwo", 'language' => Language::PlainText]);
    app(SnippetRenderer::class)->refresh($snippet);

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->assertDontSee('Show all');
});

it('copies the raw body rather than the rendered runs', function () {
    $snippet = Snippet::factory()->create(['body' => "<?php\necho 'hi';", 'language' => Language::Php]);
    app(SnippetRenderer::class)->refresh($snippet);

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->press('copy')
        ->assertCopied("<?php\necho 'hi';");
});

it('confirms the copy to the user', function () {
    $snippet = Snippet::factory()->create();

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->press('copy')
        ->assertNativeCalled('Dialog.Toast');
});

it('shares the snippet as a file', function () {
    $snippet = Snippet::factory()->create(['title' => 'Retry helper', 'body' => 'shared body']);

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->press('share')
        ->assertNativeCalled('Share.File', fn (array $params): bool => $params['title'] === 'Retry helper');
});

it('opens the edit screen', function () {
    $snippet = Snippet::factory()->create();

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->press('edit')
        ->assertNavigatedTo("/snippets/{$snippet->id}/edit");
});

it('asks before deleting', function () {
    $snippet = Snippet::factory()->create();

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->press('confirmDelete')
        ->assertSet('confirmingDelete', true)
        ->assertSee('Delete this snippet?');

    expect(Snippet::query()->count())->toBe(1);
});

it('deletes the snippet once confirmed and returns to the list', function () {
    $snippet = Snippet::factory()->create();
    app(SnippetRenderer::class)->refresh($snippet);

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->press('confirmDelete')
        ->press('delete')
        ->assertWentBack();

    expect(Snippet::query()->count())->toBe(0)
        ->and(SnippetRender::query()->count())->toBe(0);
});

it('backs out of the delete confirmation', function () {
    $snippet = Snippet::factory()->create();

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->press('confirmDelete')
        ->press('cancelDelete')
        ->assertSet('confirmingDelete', false);

    expect(Snippet::query()->count())->toBe(1);
});

it('re-renders both themes when the language changes', function () {
    $snippet = Snippet::factory()->create(['body' => "print('hi')", 'language' => Language::Php]);
    app(SnippetRenderer::class)->refresh($snippet);

    $before = $snippet->renders()->where('theme', ThemeVariant::Light)->value('hash');

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->select('changeLanguage', 'Python');

    $snippet->refresh();

    /*
     * The hash covers body AND language, so a restamped hash is direct
     * evidence the renders were re-derived rather than left stale — and it
     * stays discriminating for source two grammars happen to colour
     * identically, which a content comparison does not.
     */
    expect($snippet->language)->toBe(Language::Python)
        ->and($snippet->renders()->count())->toBe(2)
        ->and($snippet->renders()->where('theme', ThemeVariant::Light)->value('hash'))->not->toBe($before)
        ->and(app(SnippetRenderer::class)->renderFor($snippet, ThemeVariant::Light))->not->toBeNull()
        ->and(app(SnippetRenderer::class)->renderFor($snippet, ThemeVariant::Dark))->not->toBeNull();
});

it('does not re-highlight when the picker reselects the language already in use', function () {
    $snippet = Snippet::factory()->create(['body' => "<?php\necho 'hi';", 'language' => Language::Php]);
    app(SnippetRenderer::class)->refresh($snippet);

    /*
     * Comparing the stored rows before and after cannot see this: re-deriving
     * an unchanged body under an unchanged language produces byte-identical
     * content and an identical hash, so a wasteful refresh leaves no trace.
     * Planting a render that could not have come from the highlighter makes
     * the difference visible — it survives iff nothing re-derived it. On a
     * screen where a refresh is a synchronous tokenise of both themes, that
     * difference is seconds of the user's time.
     */
    $snippet->renders()->update(['content' => [[['text' => 'PLANTED', 'color' => '#abcdef']]]]);

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->select('changeLanguage', 'PHP')
        ->assertSee('PLANTED');

    expect($snippet->fresh()->language)->toBe(Language::Php);
});

it('ignores a language the picker should never have been able to offer', function () {
    $snippet = Snippet::factory()->create(['language' => Language::Php]);
    app(SnippetRenderer::class)->refresh($snippet);

    $snippet->renders()->update(['content' => [[['text' => 'PLANTED', 'color' => '#abcdef']]]]);

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->select('changeLanguage', 'COBOL')
        ->assertSee('PLANTED');

    expect($snippet->fresh()->language)->toBe(Language::Php);
});

it('reads in the dark render when the system is dark', function () {
    $snippet = Snippet::factory()->create(['body' => "<?php\necho 'hi';", 'language' => Language::Php]);
    app(SnippetRenderer::class)->refresh($snippet);

    System::rememberAppearance('dark');

    $colours = renderedColours(
        Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])->tree()
    );

    expect($colours)->toContain('#e1e4e8');
});

it('shows a readable message when the snippet no longer exists', function () {
    Native::test(SnippetShowScreen::class, ['snippet' => '404'])
        ->assertSee('That snippet is no longer here');
});
