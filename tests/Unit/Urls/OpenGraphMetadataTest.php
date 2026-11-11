<?php

uses(Tests\TestCase::class);

use App\Support\Urls\OpenGraphMetadata;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

it('extracts the supported open graph properties from html', function () {
    Http::fake([
        'https://example.com/*' => Http::response(<<<'HTML'
            <html><head>
                <meta property="og:title" content="Example title">
                <meta property="og:description" content="Example description">
                <meta property="og:image" content="https://example.com/image.png">
                <meta property="og:site_name" content="Example site">
            </head></html>
            HTML, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
    ]);

    expect(app(OpenGraphMetadata::class)->fetch('https://example.com/page'))->toBe([
        'title' => 'Example title',
        'description' => 'Example description',
        'image' => 'https://example.com/image.png',
        'siteName' => 'Example site',
    ]);
});

it('returns no metadata for incomplete or malformed responses', function () {
    Http::fake([
        'https://example.com/*' => Http::response('<html><head><title>Only title</title></head></html>', 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    expect(app(OpenGraphMetadata::class)->fetch('https://example.com/page'))->toBeNull();
});

it('does not parse oversized responses', function () {
    Http::fake([
        'https://example.com/*' => Http::response(str_repeat('x', 1_000_001), 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    expect(app(OpenGraphMetadata::class)->fetch('https://example.com/page'))->toBeNull();
});

it('caches metadata after the first request', function () {
    $requests = 0;

    Http::fake(function () use (&$requests) {
        $requests++;

        return Http::response('<meta property="og:title" content="Cached title">', 200, [
            'Content-Type' => 'text/html',
        ]);
    });

    $fetcher = app(OpenGraphMetadata::class);

    expect($fetcher->fetch('https://example.com/page'))->toBe(['title' => 'Cached title'])
        ->and($fetcher->fetch('https://example.com/page'))->toBe(['title' => 'Cached title'])
        ->and($requests)->toBe(1);
});

it('rejects unsupported and private urls before fetching', function () {
    Http::fake();
    $fetcher = app(OpenGraphMetadata::class);

    expect($fetcher->fetch('javascript:alert(1)'))->toBeNull()
        ->and($fetcher->fetch('http://127.0.0.1/private'))->toBeNull();

    Http::assertNothingSent();
});
