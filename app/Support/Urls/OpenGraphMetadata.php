<?php

namespace App\Support\Urls;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class OpenGraphMetadata
{
    private const MAX_RESPONSE_BYTES = 1_000_000;

    private const CACHE_VERSION = 'v2';

    /** @var Closure(string): list<string> */
    private Closure $resolver;

    /** @param (Closure(string): list<string>)|null $resolver */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? function (string $host): array {
            $addresses = gethostbynamel($host) ?: [];

            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }

            return array_values(array_unique($addresses));
        };
    }

    /** @return array{title?: string, description?: string, image?: string, siteName?: string}|null */
    public function fetch(string $url): ?array
    {
        $address = $this->validatedAddress($url);

        if ($address === null) {
            return null;
        }

        $key = 'open_graph:'.self::CACHE_VERSION.':'.sha1($url);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return ($cached['_empty'] ?? false) ? null : $cached;
        }

        try {
            $metadata = $this->retrieve($url, $address);
        } catch (ConnectionException) {
            $metadata = null;
        }

        Cache::put(
            $key,
            $metadata ?? ['_empty' => true],
            now()->addMinutes($metadata === null ? 5 : 1440),
        );

        return $metadata;
    }

    /** @return array{title?: string, description?: string, image?: string, siteName?: string}|null */
    private function retrieve(string $url, string $address): ?array
    {
        $response = $this->request($this->resolveEntry($url, $address))->get($url);

        if (! $response->successful()) {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return null;
        }

        $stream = $response->toPsrResponse()->getBody();
        $html = '';

        while (true) {
            $remaining = self::MAX_RESPONSE_BYTES + 1 - strlen($html);
            $chunk = $stream->read(min(8192, $remaining));
            $html .= $chunk;

            if ($stream->eof()) {
                break;
            }

            if ($chunk === '' || $remaining <= 0) {
                return null;
            }
        }

        if (strlen($html) > self::MAX_RESPONSE_BYTES) {
            return null;
        }

        return $this->parse($html);
    }

    private function resolveEntry(string $url, string $address): string
    {
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $host = str_contains($host, ':') && ! str_starts_with($host, '[') ? '['.$host.']' : $host;
        $port = (int) ($parts['port'] ?? (strtolower((string) ($parts['scheme'] ?? '')) === 'https' ? 443 : 80));
        $resolvedAddress = str_contains($address, ':') ? '['.$address.']' : $address;

        return $host.':'.$port.':'.$resolvedAddress;
    }

    private function request(string $resolvedHost): PendingRequest
    {
        return Http::timeout(5)
            ->connectTimeout(2)
            ->withOptions([
                'allow_redirects' => false,
                'stream' => true,
                'curl' => [CURLOPT_RESOLVE => [$resolvedHost]],
            ])
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
                if ($field === 'image' && ! $this->isSafeUrl($content)) {
                    continue;
                }

                $metadata[$field] ??= mb_substr($content, 0, 5000);
            }
        }

        return $metadata === [] ? null : $metadata;
    }

    private function isSafeUrl(string $url): bool
    {
        return $this->validatedAddress($url) !== null;
    }

    private function validatedAddress(string $url): ?string
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = trim($host, '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            return null;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP);

        if ($ip !== false) {
            return $this->isPublicIp($ip) ? $ip : null;
        }

        $addresses = ($this->resolver)($host);

        if ($addresses === false || $addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicIp($address)) {
                return null;
            }
        }

        return $addresses[0];
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
