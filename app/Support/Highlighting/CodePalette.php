<?php

namespace App\Support\Highlighting;

use App\Enums\ThemeVariant;

/**
 * Maps syntax roles to the same colors used by Codepad's native UI theme.
 *
 * The palette deliberately reads the configured hex values instead of
 * introducing a second set of brighter or darker syntax colors. The selected
 * code theme is independent from the device appearance, so it must address
 * the light/dark config block directly rather than use the `theme()` helper.
 */
final class CodePalette
{
    /** @return array<string, mixed> */
    private function colors(ThemeVariant $theme): array
    {
        return config("native-ui.theme.{$theme->value}", []);
    }

    /** @return array<string, mixed> */
    public function definition(ThemeVariant $theme): array
    {
        $colors = $this->colors($theme);
        $syntax = $colors['syntax'];

        return [
            'name' => $theme->syntaxTheme(),
            'type' => $theme === ThemeVariant::Dark ? 'dark' : 'light',
            'colors' => [
                'editor.background' => $colors['surface'],
                'editor.foreground' => $colors['on-surface'],
            ],
            'tokenColors' => [
                ['scope' => ['comment', 'punctuation.definition.comment'], 'settings' => ['foreground' => $syntax['comment']]],
                ['scope' => ['string'], 'settings' => ['foreground' => $syntax['string']]],
                ['scope' => ['constant', 'number'], 'settings' => ['foreground' => $syntax['number']]],
                ['scope' => ['keyword', 'storage'], 'settings' => ['foreground' => $syntax['keyword']]],
                ['scope' => ['entity.name.function', 'support.function'], 'settings' => ['foreground' => $syntax['function']]],
                ['scope' => ['meta.property-name', 'support.type.property-name'], 'settings' => ['foreground' => $colors['primary']]],
                ['scope' => ['support.constant.color', 'meta.property-value'], 'settings' => ['foreground' => $colors['accent']]],
                ['scope' => ['markup.heading', 'entity.name.section', 'markup.list', 'markup.fenced_code'], 'settings' => ['foreground' => $colors['primary']]],
                ['scope' => ['markup.bold', 'markup.italic'], 'settings' => ['foreground' => $colors['accent']]],
                ['scope' => ['string.other.link', 'markup.underline.link'], 'settings' => ['foreground' => $colors['success']]],
                ['scope' => ['punctuation.definition.heading', 'punctuation.definition.markdown'], 'settings' => ['foreground' => $colors['primary']]],
                ['scope' => ['invalid'], 'settings' => ['foreground' => $colors['destructive']]],
            ],
        ];
    }
}
