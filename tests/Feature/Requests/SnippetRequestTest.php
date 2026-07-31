<?php

use App\Enums\Language;
use App\Http\Requests\StoreSnippetRequest;
use Illuminate\Support\Facades\Validator;

function validateSnippet(array $data): Illuminate\Contracts\Validation\Validator
{
    return Validator::make($data, (new StoreSnippetRequest)->rules());
}

it('accepts a minimal snippet', function () {
    expect(validateSnippet(['body' => 'echo 1;', 'language' => Language::Php->value])->passes())->toBeTrue();
});

it('accepts a null title', function () {
    expect(validateSnippet(['title' => null, 'body' => 'x', 'language' => 'php'])->passes())->toBeTrue();
});

it('requires a body', function () {
    expect(validateSnippet(['body' => '', 'language' => 'php'])->passes())->toBeFalse();
});

it('rejects a body over 100 KB', function () {
    expect(validateSnippet(['body' => str_repeat('a', 102401), 'language' => 'php'])->passes())->toBeFalse();
});

it('accepts a body at exactly 100 KB', function () {
    expect(validateSnippet(['body' => str_repeat('a', 102400), 'language' => 'php'])->passes())->toBeTrue();
});

it('rejects a language outside the allowlist', function () {
    expect(validateSnippet(['body' => 'x', 'language' => 'cobol'])->passes())->toBeFalse();
});
