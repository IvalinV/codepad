<?php

use App\Native\SnippetListScreen;
use Illuminate\Support\Facades\Route;

/*
| Every screen in Codepad is a native SuperNative component — there is no
| web UI. `Route::native()` registers the component with the native router
| and an HTTP route alongside it, which is what the device's runtime hits
| on launch.
*/

Route::native('/', SnippetListScreen::class);
