<?php

use App\Enums\Language;
use App\Http\Requests\StoreSnippetRequest;
use App\Http\Requests\UpdateSnippetRequest;
use Illuminate\Support\Facades\Validator;

/** @param  class-string  $requestClass */
function validateSnippet(string $requestClass, array $data): Illuminate\Contracts\Validation\Validator
{
    return Validator::make($data, (new $requestClass)->rules());
}

dataset('snippet request classes', [
    'StoreSnippetRequest' => [StoreSnippetRequest::class],
    'UpdateSnippetRequest' => [UpdateSnippetRequest::class],
]);

it('accepts a minimal snippet', function (string $requestClass) {
    expect(validateSnippet($requestClass, ['body' => 'echo 1;', 'language' => Language::Php->value])->passes())->toBeTrue();
})->with('snippet request classes');

it('accepts a null title', function (string $requestClass) {
    expect(validateSnippet($requestClass, ['title' => null, 'body' => 'x', 'language' => 'php'])->passes())->toBeTrue();
})->with('snippet request classes');

it('requires a body', function (string $requestClass) {
    expect(validateSnippet($requestClass, ['body' => '', 'language' => 'php'])->passes())->toBeFalse();
})->with('snippet request classes');

it('rejects a body over 100 KB', function (string $requestClass) {
    expect(validateSnippet($requestClass, ['body' => str_repeat('a', 102401), 'language' => 'php'])->passes())->toBeFalse();
})->with('snippet request classes');

it('accepts a body at exactly 100 KB', function (string $requestClass) {
    expect(validateSnippet($requestClass, ['body' => str_repeat('a', 102400), 'language' => 'php'])->passes())->toBeTrue();
})->with('snippet request classes');

it('rejects a language outside the allowlist', function (string $requestClass) {
    expect(validateSnippet($requestClass, ['body' => 'x', 'language' => 'cobol'])->passes())->toBeFalse();
})->with('snippet request classes');
