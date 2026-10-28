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
    /** @return array<string, string> */
    private function colors(ThemeVariant $theme): array
    {
        return config("native-ui.theme.{$theme->value}", []);
    }

    /** @return array<string, mixed> */
    public function definition(ThemeVariant $theme): array
    {
        $colors = $this->colors($theme);

        return [
            'name' => $theme->syntaxTheme(),
            'type' => $theme === ThemeVariant::Dark ? 'dark' : 'light',
            'colors' => [
                'editor.background' => $colors['surface'],
                'editor.foreground' => $colors['on-surface'],
            ],
            'tokenColors' => [
                ['scope' => ['comment', 'punctuation.definition.comment'], 'settings' => ['foreground' => $colors['on-surface-variant']]],
                ['scope' => ['string'], 'settings' => ['foreground' => $colors['success']]],
                ['scope' => ['constant', 'number'], 'settings' => ['foreground' => $colors['accent']]],
                ['scope' => ['keyword', 'storage'], 'settings' => ['foreground' => $colors['primary']]],
                ['scope' => ['entity.name.function', 'support.function'], 'settings' => ['foreground' => $colors['primary']]],
                ['scope' => ['invalid'], 'settings' => ['foreground' => $colors['destructive']]],
            ],
        ];
    }
}
