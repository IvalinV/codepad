<?php

use App\Enums\ThemeVariant;
use Phiki\Theme\Theme;

it('has exactly two variants', function () {
    expect(ThemeVariant::cases())->toHaveCount(2);
});

it('maps each variant to a phiki theme', function (ThemeVariant $variant) {
    expect($variant->phikiTheme())->toBeInstanceOf(Theme::class);
})->with(ThemeVariant::cases());
