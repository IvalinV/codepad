<?php

use App\Models\Snippet;
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
| The harness ships a real audit — icon-only controls with nothing to
| announce, inputs with no label, pressables whose subtree holds no text.
| Every one of those is invisible on the device unless you are using a screen
| reader, so a test is the only thing that will ever catch them: three were
| already here when this file was written (the capture FAB, and the language
| picker on both screens that carry one).
|
| Screens are enumerated explicitly rather than swept from the route registry
| because each needs its own fixtures, and a sweep that silently skipped a
| screen it could not build would be worse than no test at all. The last case
| guards that the list stays complete.
*/

it('has no accessibility violations on the library', function () {
    Snippet::factory()->count(3)->create();

    Native::test(SnippetListScreen::class)->assertAccessible();
});

it('has no accessibility violations on the library when it is empty', function () {
    Native::test(SnippetListScreen::class)->assertAccessible();
});

it('has no accessibility violations on the library when filters match nothing', function () {
    Snippet::factory()->create();

    Native::test(SnippetListScreen::class)
        ->input('search', 'matches nothing at all')
        ->assertAccessible();
});

it('has no accessibility violations while reading a snippet', function () {
    $snippet = Snippet::factory()->create();

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->assertAccessible();
});

it('has no accessibility violations in the delete confirmation', function () {
    $snippet = Snippet::factory()->create();

    Native::test(SnippetShowScreen::class, ['snippet' => (string) $snippet->id])
        ->press('confirmDelete')
        ->assertAccessible();
});

it('has no accessibility violations when a snippet is gone', function () {
    Native::test(SnippetShowScreen::class, ['snippet' => '404'])->assertAccessible();
});

it('has no accessibility violations while capturing', function () {
    Native::test(SnippetEditScreen::class)->assertAccessible();
});

it('has no accessibility violations while editing', function () {
    $snippet = Snippet::factory()->create();

    Native::test(SnippetEditScreen::class, ['snippet' => (string) $snippet->id])
        ->assertAccessible();
});

it('has no accessibility violations when the editor is showing an error', function () {
    Native::test(SnippetEditScreen::class)
        ->set('body', '')
        ->press('save')
        ->assertAccessible();
});

it('has no accessibility violations in settings', function () {
    Native::test(SettingsScreen::class)->assertAccessible();
});

it('has no accessibility violations on a settings message', function () {
    Native::test(SettingsScreen::class)
        ->set('backup', 'not a backup')
        ->press('import')
        ->assertAccessible();
});

it('covers every registered screen, so a new one cannot skip the audit', function () {
    $audited = [
        SnippetListScreen::class,
        SnippetShowScreen::class,
        SnippetEditScreen::class,
        SettingsScreen::class,
    ];

    $registered = array_unique(array_column(NativeRouter::registeredRoutes(), 'class'));

    expect(array_values(array_diff($registered, $audited)))->toBe([]);
});
