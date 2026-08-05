<?php

namespace App\Support;

use App\Enums\Language;
use App\Enums\ThemeVariant;
use Illuminate\Support\Facades\Cache;
use Native\Mobile\Facades\System;

/**
 * The two things Codepad remembers about how you like to work: the language
 * you last saved with, and which theme you read in.
 *
 * These live in the cache rather than a settings table. `CACHE_STORE` is
 * `database` and the database is the app's own SQLite file, so a cache entry
 * is as durable as a table row would be — without a migration, a model, and a
 * row-per-key schema for what is two keys. `forever()` because a preference
 * that expires is a preference the user has to set twice.
 */
final class Preferences
{
    private const LAST_LANGUAGE = 'codepad.last-language';

    private const THEME = 'codepad.theme';

    /**
     * Stored in place of a `ThemeVariant` value when the user wants the OS to
     * decide. A sentinel rather than a missing key so "never chosen" and
     * "deliberately chose to follow the system" stay distinguishable.
     */
    private const FOLLOW_SYSTEM = 'system';

    /**
     * The language a new snippet should default to. Plain text is the honest
     * default before the user has saved anything — it is the one language
     * that is never wrong.
     */
    public function lastLanguage(): Language
    {
        return Language::tryFrom((string) Cache::get(self::LAST_LANGUAGE)) ?? Language::PlainText;
    }

    public function rememberLanguage(Language $language): void
    {
        Cache::forever(self::LAST_LANGUAGE, $language->value);
    }

    /** The user's explicit choice, or null when they follow the system. */
    public function themePreference(): ?ThemeVariant
    {
        return ThemeVariant::tryFrom((string) Cache::get(self::THEME, self::FOLLOW_SYSTEM));
    }

    /** Pass null to follow the system appearance. */
    public function rememberTheme(?ThemeVariant $theme): void
    {
        Cache::forever(self::THEME, $theme?->value ?? self::FOLLOW_SYSTEM);
    }

    /**
     * The theme to read snippets in right now. Both themes are rendered at
     * save time, so resolving this is a lookup rather than a re-highlight —
     * which is what makes following the system appearance free.
     */
    public function activeTheme(): ThemeVariant
    {
        return $this->themePreference()
            ?? (System::isDarkMode() ? ThemeVariant::Dark : ThemeVariant::Light);
    }
}
