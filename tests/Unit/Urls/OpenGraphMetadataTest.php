<?php

use App\Support\Urls\OpenGraphMetadata;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use Tests\TestCase;

uses(TestCase::class);

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

it('reads streamed html until the response ends', function () {
    $stream = Mockery::mock(StreamInterface::class);
    $stream->shouldReceive('read')
        ->twice()
        ->andReturn(
            '<meta property="og:title" content="Chunked title">',
            '<meta property="og:description" content="Chunked description">',
        );
    $stream->shouldReceive('eof')->twice()->andReturn(false, true);

    Http::fake([
        'https://example.com/*' => Http::response($stream, 200, ['Content-Type' => 'text/html']),
    ]);

    expect(app(OpenGraphMetadata::class)->fetch('https://example.com/page'))->toBe([
        'title' => 'Chunked title',
        'description' => 'Chunked description',
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

it('returns no metadata when the remote request fails', function () {
    Http::fake(function () {
        throw new ConnectionException('connection failed');
    });

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

it('does not reuse a stale negative cache entry from the previous fetcher', function () {
    Cache::put('open_graph:'.sha1('https://example.com/page'), ['_empty' => true], 300);
    Http::fake([
        'https://example.com/*' => Http::response('<meta property="og:title" content="Fresh title">', 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    expect(app(OpenGraphMetadata::class)->fetch('https://example.com/page'))->toBe([
        'title' => 'Fresh title',
    ]);
});

it('rejects unsupported and private urls before fetching', function () {
    Http::fake();
    $fetcher = app(OpenGraphMetadata::class);

    expect($fetcher->fetch('javascript:alert(1)'))->toBeNull()
        ->and($fetcher->fetch('http://127.0.0.1/private'))->toBeNull();

    Http::assertNothingSent();
});

it('supports a public ipv6-only hostname', function () {
    Http::fake([
        'https://example.com/*' => Http::response('<meta property="og:title" content="IPv6 title">', 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    $fetcher = new OpenGraphMetadata(fn (string $host): array => [
        '2606:4700:4700::1111',
    ]);

    expect($fetcher->fetch('https://example.com/page'))->toBe(['title' => 'IPv6 title']);
});

it('supports a public ipv6 literal', function () {
    Http::fake([
        'https://[2606:4700:4700::1111]/*' => Http::response('<meta property="og:title" content="IPv6 literal title">', 200, [
            'Content-Type' => 'text/html',
        ]),
    ]);

    expect(app(OpenGraphMetadata::class)->fetch('https://[2606:4700:4700::1111]/page'))
        ->toBe(['title' => 'IPv6 literal title']);
});

it('formats ipv6 resolve entries with a bracketed host', function () {
    $method = new ReflectionMethod(OpenGraphMetadata::class, 'resolveEntry');

    expect($method->invoke(
        app(OpenGraphMetadata::class),
        'https://[2606:4700:4700::1111]/page',
        '2606:4700:4700::1111',
    ))->toBe('[2606:4700:4700::1111]:443:[2606:4700:4700::1111]');
});

it('rejects a hostname that resolves to a private address', function () {
    Http::fake();

    $fetcher = new OpenGraphMetadata(fn (string $host): array => [
        '127.0.0.1',
    ]);

    expect($fetcher->fetch('https://example.com/page'))->toBeNull();

    Http::assertNothingSent();
});

it('omits unsafe open graph images while keeping safe metadata', function () {
    Http::fake([
        'https://example.com/*' => Http::response(<<<'HTML'
            <meta property="og:title" content="Example title">
            <meta property="og:image" content="file:///private/image.png">
            HTML, 200, ['Content-Type' => 'text/html']),
    ]);

    expect(app(OpenGraphMetadata::class)->fetch('https://example.com/page'))->toBe([
        'title' => 'Example title',
    ]);
});
