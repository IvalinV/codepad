<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Support\Preferences;
use Native\Mobile\System;

/*
| `System::$appearance` is a process-wide static seeded from a bridge probe
| the first time it is read. Off-device that probe reaches for the Jump TCP
| bridge and blocks until it times out, and whatever it settles on then leaks
| into every later test in the process. `rememberAppearance()` is the same
| entry point the framework's own AppearanceChanged listener uses, so seeding
| it here both pins the value and exercises the supported path.
*/
beforeEach(function () {
    System::rememberAppearance('light');

    $this->preferences = new Preferences;
});

it('defaults to plain text before anything has been saved', function () {
    expect($this->preferences->lastLanguage())->toBe(Language::PlainText);
});

it('remembers the last language used', function () {
    $this->preferences->rememberLanguage(Language::Rust);

    expect((new Preferences)->lastLanguage())->toBe(Language::Rust);
});

it('falls back to plain text when the stored language is no longer in the allowlist', function () {
    cache()->forever('codepad.last-language', 'cobol');

    expect($this->preferences->lastLanguage())->toBe(Language::PlainText);
});

it('follows the system appearance until the user chooses a theme', function () {
    expect($this->preferences->themePreference())->toBeNull()
        ->and($this->preferences->activeTheme())->toBe(ThemeVariant::Light);

    System::rememberAppearance('dark');

    expect($this->preferences->activeTheme())->toBe(ThemeVariant::Dark);
});

it('remembers an explicit theme choice', function () {
    $this->preferences->rememberTheme(ThemeVariant::Dark);

    expect((new Preferences)->themePreference())->toBe(ThemeVariant::Dark)
        ->and((new Preferences)->activeTheme())->toBe(ThemeVariant::Dark);
});

it('keeps an explicit choice even when the system disagrees', function () {
    $this->preferences->rememberTheme(ThemeVariant::Light);

    System::rememberAppearance('dark');

    expect($this->preferences->activeTheme())->toBe(ThemeVariant::Light);
});

it('goes back to following the system when the choice is cleared', function () {
    $this->preferences->rememberTheme(ThemeVariant::Dark);
    $this->preferences->rememberTheme(null);

    expect((new Preferences)->themePreference())->toBeNull()
        ->and((new Preferences)->activeTheme())->toBe(ThemeVariant::Light);
});
