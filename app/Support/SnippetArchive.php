<?php

namespace App\Support;

use App\Enums\Language;
use App\Models\Snippet;
use InvalidArgumentException;

final class SnippetArchive
{
    private const VERSION = 1;

    public function export(): string
    {
        $snippets = Snippet::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Snippet $snippet): array => [
                'title' => $snippet->title,
                'body' => $snippet->body,
                'language' => $snippet->language->value,
                'created_at' => $snippet->created_at?->toIso8601String(),
                'updated_at' => $snippet->updated_at?->toIso8601String(),
            ])
            ->all();

        return json_encode(
            ['version' => self::VERSION, 'snippets' => $snippets],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    public function import(string $json): int
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! isset($decoded['snippets']) || ! is_array($decoded['snippets'])) {
            throw new InvalidArgumentException('That does not look like a Codepad backup.');
        }

        if (($decoded['version'] ?? null) !== self::VERSION) {
            throw new InvalidArgumentException('That backup was made by an incompatible version of Codepad.');
        }

        $imported = 0;

        foreach ($decoded['snippets'] as $entry) {
            $language = Language::tryFrom($entry['language'] ?? '');

            if (! $language instanceof Language || ! isset($entry['body'])) {
                continue;
            }

            Snippet::query()->create([
                'title' => $entry['title'] ?? null,
                'body' => $entry['body'],
                'language' => $language,
            ]);

            $imported++;
        }

        return $imported;
    }
}
