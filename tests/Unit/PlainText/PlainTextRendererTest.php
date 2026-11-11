<?php

use App\Support\PlainText\PlainTextRenderer;

it('detects multiple plain text urls without trailing punctuation', function () {
    $segments = app(PlainTextRenderer::class)->render(
        "Read https://example.com/docs, then visit http://example.test/path."
    );

    expect($segments)->toBe([
        ['type' => 'text', 'text' => 'Read '],
        ['type' => 'link', 'text' => 'https://example.com/docs', 'url' => 'https://example.com/docs'],
        ['type' => 'text', 'text' => ', then visit '],
        ['type' => 'link', 'text' => 'http://example.test/path', 'url' => 'http://example.test/path'],
        ['type' => 'text', 'text' => '.'],
    ]);
});

it('keeps unsupported schemes as plain text', function () {
    $segments = app(PlainTextRenderer::class)->render('javascript:alert(1) file:///tmp/test mailto:test@example.com');

    expect($segments)->toBe([
        ['type' => 'text', 'text' => 'javascript:alert(1) file:///tmp/test mailto:test@example.com'],
    ]);
});
