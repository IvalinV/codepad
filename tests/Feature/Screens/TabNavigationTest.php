<?php

use App\Models\Snippet;
use App\Native\Layouts\TabsLayout;
use App\Native\SettingsScreen;
use App\Native\SnippetEditScreen;
use App\Native\SnippetListScreen;
use App\Native\SnippetShowScreen;
use Native\Mobile\Edge\NativeRouter;
use Native\Mobile\System;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    System::rememberAppearance('light');
});

/*
| The routes file is the single source of truth for which screens are AREAS
| (bottom tab bar) and which are pushed. Reading the registry back is what
| catches a screen being added to the wrong side of the group — the mistake
| that is invisible until someone is holding the phone.
*/

it('carries the tab bar on every area of the app', function (string $uri, string $screen) {
    $route = NativeRouter::resolve($uri);

    expect($route)->not->toBeNull()
        ->and($route['class'])->toBe($screen)
        ->and($route['layout'])->toBe(TabsLayout::class);
})->with([
    'library' => ['/', SnippetListScreen::class],
    'capture' => ['/snippets/new', SnippetEditScreen::class],
    'settings' => ['/settings', SettingsScreen::class],
]);

it('leaves the pushed screens without a tab bar', function (string $uri, string $screen) {
    $route = NativeRouter::resolve($uri);

    expect($route)->not->toBeNull()
        ->and($route['class'])->toBe($screen)
        ->and($route['layout'])->toBeNull();
})->with([
    'reading a snippet' => ['/snippets/7', SnippetShowScreen::class],
    'editing a snippet' => ['/snippets/7/edit', SnippetEditScreen::class],
]);

it('offers exactly the three areas, each pointing at a registered route', function () {
    $tabs = (new TabsLayout)->tabBar(new SnippetListScreen)->getTabs();

    expect($tabs)->toHaveCount(3);

    foreach ($tabs as $tab) {
        expect(NativeRouter::resolve($tab->getUrl()))->not->toBeNull();
    }

    expect(array_map(fn ($tab): string => $tab->getUrl(), $tabs))
        ->toBe(['/', '/snippets/new', '/settings']);
});

it('renders the tab bar as native chrome rather than an in-tree row', function () {
    $tree = Native::test(SnippetListScreen::class, layout: TabsLayout::class)->tree();

    $encoded = json_encode($tree);

    expect($encoded)->toContain('Snippets')
        ->and($encoded)->toContain('Capture')
        ->and($encoded)->toContain('Settings');
});

/*
| Capture is a tab, so the edit screen can be the ROOT of its stack — and
| back() at the root pops the last frame and exits the app. These two pin the
| asymmetry that protects against it.
*/

it('replaces onto the library when a capture is finished, rather than popping out of the app', function () {
    Native::test(SnippetEditScreen::class)
        ->set('body', 'captured from the tab')
        ->press('save')
        ->assertReplacedWith('/');
});

it('pops back to the snippet when an edit is finished, because editing is always a push', function () {
    $snippet = Snippet::factory()->create();

    Native::test(SnippetEditScreen::class, ['snippet' => (string) $snippet->id])
        ->set('body', 'edited from the read screen')
        ->press('save')
        ->assertWentBack();
});

it('abandons a capture the same way it finishes one', function () {
    Native::test(SnippetEditScreen::class)
        ->press('cancel')
        ->assertReplacedWith('/');

    expect(Snippet::query()->count())->toBe(0);
});
