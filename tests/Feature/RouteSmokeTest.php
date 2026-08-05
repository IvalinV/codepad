<?php

use App\Models\Snippet;
use Native\Mobile\Edge\NativeRouter;

/*
| `Native::test()` drives components directly and never touches HTTP. But the
| device does: `Route::native()` registers a real GET route alongside the
| component, and that is what the runtime hits on launch — through the whole
| `web` middleware stack, session included.
|
| So this is the one test that exercises the path the app actually boots
| through. It replaces the starter's ExampleTest, which asserted the same
| thing about `/` alone and by accident.
|
| The URIs are written out rather than read from `NativeRouter` because a
| Pest dataset is resolved at COLLECTION time, before the application boots
| and before any route is registered — a closure reading the registry there
| yields an empty set, which passes as zero cases. The last test closes that
| by comparing the list against the live registry inside a booted test.
*/

it('serves every registered screen over HTTP, the way the device boots into them', function (string $uri) {
    Snippet::factory()->create();

    $this->get($uri)->assertSuccessful();
})->with([
    'library' => '/',
    'capture' => '/snippets/new',
    'settings' => '/settings',
    'reading a snippet' => '/snippets/1',
    'editing a snippet' => '/snippets/1/edit',
]);

it('smoke-tests every route that is actually registered', function () {
    $smokeTested = ['/', '/snippets/new', '/settings', '/snippets/{snippet}', '/snippets/{snippet}/edit'];

    expect(array_keys(NativeRouter::registeredRoutes()))
        ->toEqualCanonicalizing($smokeTested);
});

it('answers the health check', function () {
    $this->get('/up')->assertSuccessful();
});
