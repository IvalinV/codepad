<?php

namespace App\Support\Highlighting;

use Phiki\Token\HighlightedToken;

final class PhikiTokenMapper
{
    /**
     * Last-resort colour used only when a token has no resolved foreground
     * AND no theme base colour was supplied (or the theme itself declares no
     * `editor.foreground`). Prefer passing $themeForeground instead of relying
     * on this — it is wrong on every dark theme.
     */
    private const DEFAULT_COLOR = '#000000';

    /**
     * @param  array<int, array<int, HighlightedToken>>  $highlightedTokens  Phiki's per-line highlighted tokens, as returned by Phiki::codeToHighlightedTokens()
     * @param  ?string  $themeForeground  The active theme's base foreground colour, used for tokens
     *                                    that carry no scope-specific match (e.g. statement terminators,
     *                                    plain prose). Resolve it from the same `Phiki\Theme\Theme` case
     *                                    used to produce $highlightedTokens via:
     *                                    `(new Phiki)->environment()->themes->resolve($theme)->base()->foreground`.
     *                                    Falls back to self::DEFAULT_COLOR when omitted or null.
     */
    public function map(array $highlightedTokens, ?string $themeForeground = null): HighlightedCode
    {
        $lines = [];

        foreach ($highlightedTokens as $lineTokens) {
            $runs = [];

            foreach ($lineTokens as $highlightedToken) {
                $text = rtrim($highlightedToken->token->text, "\r\n");

                if ($text === '') {
                    continue;
                }

                $runs[] = [
                    'text' => $text,
                    'color' => $this->resolveColor($highlightedToken, $themeForeground),
                ];
            }

            $lines[] = $runs;
        }

        return HighlightedCode::fromArray($lines);
    }

    /**
     * The same shape `map()` produces, for source Phiki could not tokenise at
     * all: one run per line, uncoloured beyond the theme's base foreground.
     * Lines are split with the pattern `Phiki\TextMate\Tokenizer` uses, so the
     * line count matches what a successful highlight would have returned, and
     * concatenating the runs reproduces $code exactly.
     *
     * @param  ?string  $themeForeground  As on map(); falls back to self::DEFAULT_COLOR when omitted or null.
     */
    public function mapPlainText(string $code, ?string $themeForeground = null): HighlightedCode
    {
        $color = $themeForeground ?? self::DEFAULT_COLOR;

        $lines = array_map(
            fn (string $line): array => $line === '' ? [] : [['text' => $line, 'color' => $color]],
            array_values(preg_split("/\R/u", $code)),
        );

        return HighlightedCode::fromArray($lines);
    }

    private function resolveColor(HighlightedToken $highlightedToken, ?string $themeForeground): string
    {
        $settings = array_values($highlightedToken->settings)[0] ?? null;

        return $settings?->foreground ?? $themeForeground ?? self::DEFAULT_COLOR;
    }
}
