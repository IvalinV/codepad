<?php

use App\Enums\Language;
use Phiki\Grammar\Grammar;

it('maps every case to a phiki grammar', function (Language $language) {
    expect($language->grammar())->toBeInstanceOf(Grammar::class);
})->with(Language::cases());

it('gives every case a human label', function (Language $language) {
    expect($language->label())->not->toBeEmpty();
})->with(Language::cases());

it('resolves from a stored string value', function () {
    expect(Language::tryFrom('php'))->toBe(Language::Php)
        ->and(Language::tryFrom('nonsense'))->toBeNull();
});
