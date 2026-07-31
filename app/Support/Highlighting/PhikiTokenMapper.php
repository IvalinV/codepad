<?php

namespace App\Support\Highlighting;

use Phiki\Token\HighlightedToken;

final class PhikiTokenMapper
{
    private const DEFAULT_COLOR = '#000000';

    /**
     * @param  array<int, array<int, HighlightedToken>>  $highlightedTokens  Phiki's per-line highlighted tokens, as returned by Phiki::codeToHighlightedTokens()
     */
    public function map(array $highlightedTokens): HighlightedCode
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
                    'color' => $this->resolveColor($highlightedToken),
                ];
            }

            $lines[] = $runs;
        }

        return HighlightedCode::fromArray($lines);
    }

    private function resolveColor(HighlightedToken $highlightedToken): string
    {
        $settings = array_values($highlightedToken->settings)[0] ?? null;

        return $settings?->foreground ?? self::DEFAULT_COLOR;
    }
}
