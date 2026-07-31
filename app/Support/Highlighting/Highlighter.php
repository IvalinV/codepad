<?php

namespace App\Support\Highlighting;

use App\Enums\Language;
use App\Enums\ThemeVariant;
use Phiki\Phiki;

/**
 * The only class in the application that *calls* Phiki: `highlight()` accepts
 * and returns application types only, and Phiki appears solely in the
 * constructor's dependencies. Phiki types also surface on
 * `Language::grammar()` and `ThemeVariant::phikiTheme()` — those two enum
 * methods, alongside this class, are the full replacement surface if Phiki
 * is ever swapped out.
 */
final readonly class Highlighter
{
    public function __construct(
        private Phiki $phiki,
        private PhikiTokenMapper $mapper,
    ) {}

    public function highlight(string $code, Language $language, ThemeVariant $theme): HighlightedCode
    {
        $phikiTheme = $theme->phikiTheme();

        $highlightedTokens = $this->phiki->codeToHighlightedTokens($code, $language->grammar(), $phikiTheme);

        $themeForeground = $this->phiki->environment()->themes->resolve($phikiTheme)->base()->foreground;

        return $this->mapper->map($highlightedTokens, $themeForeground === '' ? null : $themeForeground);
    }
}
