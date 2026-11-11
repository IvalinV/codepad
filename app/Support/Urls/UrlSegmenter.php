<?php

namespace App\Support\Urls;

final class UrlSegmenter
{
    /** @return list<array{type: 'text'|'link', text: string, url?: string}> */
    public function segment(string $text): array
    {
        preg_match_all('~https?://[^\s<>\'"`]+~i', $text, $matches, PREG_OFFSET_CAPTURE);

        if ($matches[0] === []) {
            return [['type' => 'text', 'text' => $text]];
        }

        $segments = [];
        $cursor = 0;

        foreach ($matches[0] as [$candidate, $offset]) {
            $url = $this->normalizeUrl($candidate);
            $candidateLength = strlen($candidate);
            $urlLength = $url === null ? 0 : strlen($url);

            if ($url === null) {
                continue;
            }

            if ($offset > $cursor) {
                $segments[] = ['type' => 'text', 'text' => substr($text, $cursor, $offset - $cursor)];
            }

            $segments[] = [
                'type' => 'link',
                'text' => $url,
                'url' => $url,
            ];

            $cursor = $offset + $urlLength;

            if ($urlLength < $candidateLength) {
                $segments[] = [
                    'type' => 'text',
                    'text' => substr($candidate, $urlLength),
                ];
                $cursor = $offset + $candidateLength;
            }
        }

        if ($cursor < strlen($text)) {
            $segments[] = ['type' => 'text', 'text' => substr($text, $cursor)];
        }

        if ($segments === []) {
            return [['type' => 'text', 'text' => $text]];
        }

        $merged = [];

        foreach ($segments as $segment) {
            $lastIndex = array_key_last($merged);
            $previous = $lastIndex === null ? null : $merged[$lastIndex];

            if ($segment['type'] === 'text' && ($previous['type'] ?? null) === 'text') {
                $merged[$lastIndex]['text'] .= $segment['text'];
            } else {
                $merged[] = $segment;
            }
        }

        return $merged;
    }

    private function normalizeUrl(string $candidate): ?string
    {
        $url = rtrim($candidate, '.,!?:;');

        while (str_ends_with($url, ')') && substr_count($url, ')') > substr_count($url, '(')) {
            $url = substr($url, 0, -1);
        }

        while (str_ends_with($url, ']') && substr_count($url, ']') > substr_count($url, '[')) {
            $url = substr($url, 0, -1);
        }

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
