<?php

use App\Enums\ThemeVariant;
use Phiki\Theme\Theme;

it('has exactly two variants', function () {
    expect(ThemeVariant::cases())->toHaveCount(2);
});

it('maps each variant to the expected phiki theme', function (ThemeVariant $variant, Theme $expected) {
    expect($variant->phikiTheme())->toBe($expected);
})->with([
    'Light' => [ThemeVariant::Light, Theme::GithubLight],
    'Dark' => [ThemeVariant::Dark, Theme::GithubDark],
]);
