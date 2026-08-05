<?php

use App\Native\SnippetEditScreen;
use App\Native\SnippetListScreen;
use App\Native\SnippetShowScreen;
use Illuminate\Support\Facades\Route;

/*
| Every screen in Codepad is a native SuperNative component — there is no
| web UI. `Route::native()` registers the component with the native router
| and an HTTP route alongside it, which is what the device's runtime hits
| on launch.
*/

Route::native('/', SnippetListScreen::class);
Route::native('/snippets/new', SnippetEditScreen::class);
Route::native('/snippets/{snippet}', SnippetShowScreen::class);
Route::native('/snippets/{snippet}/edit', SnippetEditScreen::class);
