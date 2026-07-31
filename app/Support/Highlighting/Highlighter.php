<?php

namespace App\Support\Highlighting;

use App\Enums\Language;
use App\Enums\ThemeVariant;
use Illuminate\Support\Facades\Log;
use Phiki\Phiki;
use Throwable;

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

    /**
     * Never throws. A production bundle ships a pruned subset of Phiki's
     * grammars and themes, and a file pruned one step too far surfaces here as
     * an `ErrorException` out of `file_get_contents`. Because
     * `SnippetRenderer::refresh()` runs this synchronously on save, throwing
     * would cost the user the snippet they just captured — so a failure
     * degrades to unhighlighted plain text and is logged for `native:tail`
     * instead.
     */
    public function highlight(string $code, Language $language, ThemeVariant $theme): HighlightedCode
    {
        $phikiTheme = $theme->phikiTheme();

        try {
            $highlightedTokens = $this->phiki->codeToHighlightedTokens($code, $language->grammar(), $phikiTheme);

            return $this->mapper->map($highlightedTokens, $this->themeForeground($theme));
        } catch (Throwable $failure) {
            Log::warning('Syntax highlighting failed; falling back to plain text.', [
                'language' => $language->value,
                'theme' => $theme->value,
                'exception' => $failure::class,
                'message' => $failure->getMessage(),
            ]);

            return $this->mapper->mapPlainText($code, $this->themeForeground($theme));
        }
    }

    /**
     * The active theme's base foreground, or null when the theme cannot be
     * resolved — a missing theme file is one of the failures `highlight()`
     * recovers from, so this must not be able to throw on the fallback path.
     */
    private function themeForeground(ThemeVariant $theme): ?string
    {
        try {
            $foreground = $this->phiki->environment()->themes->resolve($theme->phikiTheme())->base()->foreground;
        } catch (Throwable) {
            return null;
        }

        return $foreground === '' ? null : $foreground;
    }
}
