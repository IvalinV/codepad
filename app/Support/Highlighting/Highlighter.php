<?php

namespace App\Support\Highlighting;

use App\Enums\Language;
use App\Enums\ThemeVariant;
use Phiki\Phiki;

/**
 * The only class in the application aware that Phiki performs syntax
 * highlighting. Every other consumer depends solely on HighlightedCode,
 * so replacing the highlighting engine means replacing only this file.
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
