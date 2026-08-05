<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| The highlighting unit tests resolve their collaborators through `app()`, so
| they need the real application container — without it they silently exercise
| an unconfigured Phiki rather than the one AppServiceProvider registers. No
| database, so no RefreshDatabase.
*/

pest()->extend(TestCase::class)->in('Unit/Highlighting');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Helpers shared across test files belong here. Note that helpers defined
| inside a test file land in the GLOBAL namespace: two files choosing the
| same name is a fatal error, not a failing test. Anything reused, or named
| generically enough to collide, should move here instead.
|
*/
