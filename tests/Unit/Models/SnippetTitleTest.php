<?php

use App\Models\Snippet;

it('prefers the user title', function () {
    $snippet = new Snippet(['title' => 'Retry helper', 'body' => 'function x() {}']);

    expect($snippet->displayTitle())->toBe('Retry helper');
});

it('falls back to the first non-blank line', function () {
    $snippet = new Snippet(['title' => null, 'body' => "\n\n   function handleRetry(): void\n{\n}"]);

    expect($snippet->displayTitle())->toBe('function handleRetry(): void');
});

it('truncates a long fallback', function () {
    $snippet = new Snippet(['title' => null, 'body' => str_repeat('a', 200)]);

    expect(mb_strlen($snippet->displayTitle()))->toBeLessThanOrEqual(60);
});

it('handles an entirely blank body', function () {
    expect((new Snippet(['title' => null, 'body' => "  \n\n  "]))->displayTitle())->toBe('Untitled snippet');
});

it('treats an empty-string title as absent', function () {
    $snippet = new Snippet(['title' => '', 'body' => 'const x = 1;']);

    expect($snippet->displayTitle())->toBe('const x = 1;');
});
