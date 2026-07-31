<?php

namespace App\Enums;

use Phiki\Theme\Theme;

enum ThemeVariant: string
{
    case Light = 'light';
    case Dark = 'dark';

    public function phikiTheme(): Theme
    {
        return match ($this) {
            self::Light => Theme::GithubLight,
            self::Dark => Theme::GithubDark,
        };
    }
}
