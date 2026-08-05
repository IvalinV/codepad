<?php

use App\Native\Layouts\TabsLayout;
use App\Native\SettingsScreen;
use App\Native\SnippetEditScreen;
use App\Native\SnippetListScreen;
use App\Native\SnippetShowScreen;
use Illuminate\Support\Facades\Route;

/*
| Every screen in Codepad is a native SuperNative component — there is no
| web UI. `Route::native()` registers the component with the native router
| and an HTTP route alongside it, which is what the device's runtime hits
| on launch.
|
| The three routes inside the group are the app's AREAS and carry the
| bottom tab bar. The two outside it are pushed screens reached from an
| area — they get a back button instead, because a tab bar highlighting
| itself while the user is three levels deep inside a snippet would
| misreport where they are.
*/

Route::nativeGroup(TabsLayout::class, function (): void {
    Route::native('/', SnippetListScreen::class);
    Route::native('/snippets/new', SnippetEditScreen::class);
    Route::native('/settings', SettingsScreen::class);
});

Route::native('/snippets/{snippet}', SnippetShowScreen::class);
Route::native('/snippets/{snippet}/edit', SnippetEditScreen::class);
