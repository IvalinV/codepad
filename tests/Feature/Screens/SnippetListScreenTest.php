<?php

use App\Enums\Language;
use App\Models\Snippet;
use App\Native\SnippetListScreen;
use Native\Mobile\Testing\Native;

it('shows the empty state when nothing has been saved', function () {
    Native::test(SnippetListScreen::class)
        ->assertSee('No snippets yet')
        ->assertDontSee('No matches');
});

it('lists snippets most recently updated first', function () {
    Snippet::factory()->create(['title' => 'Older note', 'updated_at' => now()->subDay()]);
    Snippet::factory()->create(['title' => 'Newer note', 'updated_at' => now()]);

    $tree = Native::test(SnippetListScreen::class)
        ->assertSee('Newer note')
        ->assertSee('Older note')
        ->assertDontSee('No snippets yet')
        ->tree();

    $rendered = json_encode($tree);

    expect(strpos($rendered, 'Newer note'))->toBeLessThan(strpos($rendered, 'Older note'));
});

it('shows the derived title for a snippet saved without one', function () {
    Snippet::factory()->untitled()->create(['body' => "SELECT 1;\nSELECT 2;", 'language' => Language::Sql]);

    Native::test(SnippetListScreen::class)->assertSee('SELECT 1;');
});

it('shows the language label and a short body preview on each row', function () {
    Snippet::factory()->create([
        'title' => 'Handler',
        'body' => "first line\nsecond line\nthird line",
        'language' => Language::Python,
    ]);

    Native::test(SnippetListScreen::class)
        ->assertSee('Python')
        ->assertSee('first line')
        ->assertSee('second line')
        ->assertDontSee('third line');
});

it('narrows the list as the user searches', function () {
    Snippet::factory()->create(['title' => 'Retry helper']);
    Snippet::factory()->create(['title' => 'Cache warmer']);

    Native::test(SnippetListScreen::class)
        ->input('search', 'retry')
        ->assertSet('search', 'retry')
        ->assertSee('Retry helper')
        ->assertDontSee('Cache warmer');
});

/** The label on every language filter chip, in the order they are rendered. */
function chipLabels(array $tree): array
{
    $labels = [];

    $walk = function (array $node) use (&$walk, &$labels): void {
        if (($node['type'] ?? null) === 'chip') {
            $labels[] = $node['props']['label'] ?? '';
        }

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };

    $walk($tree);

    return $labels;
}

/** Every element type present anywhere in the rendered tree. */
function nodeTypes(array $tree): array
{
    $types = [];

    $walk = function (array $node) use (&$walk, &$types): void {
        $types[] = $node['type'] ?? '';

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };

    $walk($tree);

    return $types;
}

it('offers only the languages the library actually holds', function () {
    Snippet::factory()->create(['language' => Language::Php]);
    Snippet::factory()->create(['language' => Language::Go]);

    $labels = chipLabels(Native::test(SnippetListScreen::class)->tree());

    /*
     * Enum order, not storage order: `Language::cases()` puts PHP before Go.
     * Ordering by insertion or by frequency would move a chip out from under
     * the thumb that reaches for it every day, so the order is asserted here
     * rather than just the membership.
     */
    expect($labels)->toBe(['PHP', 'Go']);
});

it('hides the filter row when the library holds only one language', function () {
    Snippet::factory()->count(3)->create(['language' => Language::Php]);

    $tree = Native::test(SnippetListScreen::class)->tree();

    /*
     * A lone chip is not a filter — narrowing an all-PHP library to PHP
     * changes nothing — so the row earns its vertical space only once there
     * is a choice to make.
     *
     * The scroll view has to be gone, not merely emptied: an empty one still
     * occupies a slot in the parent column's `gap-3`, which is the very
     * vertical space hiding the row is meant to reclaim. Asserting on the
     * chips alone would pass on a row that still pushes the list down.
     */
    expect(chipLabels($tree))->toBe([])
        ->and(nodeTypes($tree))->not->toContain('scroll_view');
});

it('keeps the filter row stable while a search narrows the list', function () {
    Snippet::factory()->create(['title' => 'Retry helper', 'language' => Language::Php]);
    Snippet::factory()->create(['title' => 'Cache warmer', 'language' => Language::Go]);

    /*
     * The chips describe the library, not the current result set. Deriving
     * them from the filtered query would reflow the row on every keystroke
     * and could drop the active chip mid-typing.
     */
    $labels = chipLabels(
        Native::test(SnippetListScreen::class)
            ->input('search', 'retry')
            ->assertDontSee('Cache warmer')
            ->tree()
    );

    expect($labels)->toBe(['PHP', 'Go']);
});

it('narrows the list when a language chip is tapped', function () {
    Snippet::factory()->create(['title' => 'A php one', 'language' => Language::Php]);
    Snippet::factory()->create(['title' => 'A go one', 'language' => Language::Go]);

    Native::test(SnippetListScreen::class)
        ->press("toggleLanguage('go')")
        ->assertSet('language', 'go')
        ->assertSee('A go one')
        ->assertDontSee('A php one');
});

it('clears the language filter when the active chip is tapped again', function () {
    Snippet::factory()->create(['title' => 'A php one', 'language' => Language::Php]);
    Snippet::factory()->create(['title' => 'A go one', 'language' => Language::Go]);

    Native::test(SnippetListScreen::class)
        ->press("toggleLanguage('go')")
        ->assertSet('language', 'go')
        ->press("toggleLanguage('go')")
        ->assertSet('language', null)
        ->assertSee('A php one')
        ->assertSee('A go one');
});

it('combines the search term and the language filter', function () {
    Snippet::factory()->create(['title' => 'retry in php', 'language' => Language::Php]);
    Snippet::factory()->create(['title' => 'retry in go', 'language' => Language::Go]);
    Snippet::factory()->create(['title' => 'cache in go', 'language' => Language::Go]);

    Native::test(SnippetListScreen::class)
        ->input('search', 'retry')
        ->press("toggleLanguage('go')")
        ->assertSee('retry in go')
        ->assertDontSee('retry in php')
        ->assertDontSee('cache in go');
});

it('restores the whole list when the search is cleared', function () {
    Snippet::factory()->create(['title' => 'Retry helper']);
    Snippet::factory()->create(['title' => 'Cache warmer']);

    Native::test(SnippetListScreen::class)
        ->input('search', 'retry')
        ->assertDontSee('Cache warmer')
        ->input('search', '')
        ->assertSee('Retry helper')
        ->assertSee('Cache warmer');
});

it('distinguishes an empty library from an empty result set', function () {
    Snippet::factory()->create(['title' => 'Retry helper']);

    Native::test(SnippetListScreen::class)
        ->input('search', 'nothing matches this')
        ->assertSee('No matches')
        ->assertDontSee('No snippets yet');
});

it('offers a way out of a filtered empty state', function () {
    Snippet::factory()->create(['title' => 'Retry helper']);

    Native::test(SnippetListScreen::class)
        ->input('search', 'nothing matches this')
        ->press('clearFilters')
        ->assertSet('search', '')
        ->assertSet('language', null)
        ->assertSee('Retry helper');
});

it('opens a snippet from its row', function () {
    $snippet = Snippet::factory()->create(['title' => 'Retry helper']);

    Native::test(SnippetListScreen::class)
        ->tap('Retry helper')
        ->assertNavigatedTo("/snippets/{$snippet->id}");
});
