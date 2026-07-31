<?php

namespace App\Support\Highlighting;

use App\Enums\ThemeVariant;
use App\Models\Snippet;

/**
 * Owns the derived syntax-highlighting cache for a snippet.
 *
 * Renders are derived from (body, language, theme) and stored per theme
 * on save, so opening a snippet is a read rather than a re-tokenisation.
 * `hashFor()` guards that derivation: if the body or language changes
 * without the stored render being refreshed, `renderFor()` returns null
 * rather than serving a render for source it no longer matches.
 */
final class SnippetRenderer
{
    public function __construct(private readonly Highlighter $highlighter) {}

    public function hashFor(Snippet $snippet): string
    {
        return hash('xxh128', $snippet->body.'|'.$snippet->language->value);
    }

    public function refresh(Snippet $snippet): void
    {
        $hash = $this->hashFor($snippet);

        foreach (ThemeVariant::cases() as $theme) {
            $highlighted = $this->highlighter->highlight($snippet->body, $snippet->language, $theme);

            $snippet->renders()->updateOrCreate(
                ['theme' => $theme],
                ['content' => $highlighted->toArray(), 'hash' => $hash],
            );
        }
    }

    public function renderFor(Snippet $snippet, ThemeVariant $theme): ?HighlightedCode
    {
        $render = $snippet->renders()->firstWhere('theme', $theme);

        if ($render === null || $render->hash !== $this->hashFor($snippet)) {
            return null;
        }

        return HighlightedCode::fromArray($render->content);
    }
}
