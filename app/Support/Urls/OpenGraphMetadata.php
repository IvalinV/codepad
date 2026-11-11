<?php

namespace App\Support\Urls;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class OpenGraphMetadata
{
    private const MAX_RESPONSE_BYTES = 1_000_000;

    /** @return array{title?: string, description?: string, image?: string, siteName?: string}|null */
    public function fetch(string $url): ?array
    {
        if (! $this->isSafeUrl($url)) {
            return null;
        }

        $key = 'open_graph:'.sha1($url);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return ($cached['_empty'] ?? false) ? null : $cached;
        }

        $metadata = $this->retrieve($url);

        Cache::put(
            $key,
            $metadata ?? ['_empty' => true],
            now()->addMinutes($metadata === null ? 5 : 1440),
        );

        return $metadata;
    }

    /** @return array{title?: string, description?: string, image?: string, siteName?: string}|null */
    private function retrieve(string $url): ?array
    {
        $response = $this->request()->get($url);

        if (! $response->successful()) {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return null;
        }

        $html = $response->body();

        if (strlen($html) > self::MAX_RESPONSE_BYTES) {
            return null;
        }

        return $this->parse($html);
    }

    private function request(): PendingRequest
    {
        return Http::timeout(5)
            ->connectTimeout(2)
            ->withOptions(['allow_redirects' => false])
            ->accept('text/html');
    }

    /** @return array{title?: string, description?: string, image?: string, siteName?: string}|null */
    private function parse(string $html): ?array
    {
        if (trim($html) === '') {
            return null;
        }

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $properties = [
            'og:title' => 'title',
            'og:description' => 'description',
            'og:image' => 'image',
            'og:site_name' => 'siteName',
        ];
        $metadata = [];

        foreach ($document->getElementsByTagName('meta') as $meta) {
            $property = strtolower(trim($meta->getAttribute('property') ?: $meta->getAttribute('name')));
            $field = $properties[$property] ?? null;
            $content = trim($meta->getAttribute('content'));

            if ($field !== null && $content !== '') {
                $metadata[$field] ??= mb_substr($content, 0, 5000);
            }
        }

        return $metadata === [] ? null : $metadata;
    }

    private function isSafeUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP);

        return $ip === false || filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
