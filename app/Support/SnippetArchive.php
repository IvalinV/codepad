<?php

namespace App\Support;

use App\Enums\Language;
use App\Models\Snippet;
use InvalidArgumentException;

final class SnippetArchive
{
    private const VERSION = 1;

    /**
     * Maximum body length accepted on import, matching the `max:102400` rule
     * enforced by `StoreSnippetRequest` and `UpdateSnippetRequest`.
     */
    private const MAX_BODY_LENGTH = 102400;

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
            if (! is_array($entry) || ! $this->isImportable($entry)) {
                continue;
            }

            $language = Language::tryFrom($entry['language']);

            if (! $language instanceof Language) {
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

    /**
     * Whether a decoded archive entry has the field types required to
     * safely reach `Snippet::create()`.
     *
     * A malformed backup must degrade one entry at a time rather than
     * aborting the whole import or letting PDO silently coerce a scalar
     * `body` into a string, so every field is checked by type here rather
     * than relying on `isset()` presence alone.
     *
     * @param  array<string, mixed>  $entry
     */
    private function isImportable(array $entry): bool
    {
        $title = $entry['title'] ?? null;

        if (! is_string($title) && $title !== null) {
            return false;
        }

        if (! isset($entry['body']) || ! is_string($entry['body']) || mb_strlen($entry['body']) > self::MAX_BODY_LENGTH) {
            return false;
        }

        return isset($entry['language']) && is_string($entry['language']);
    }
}
