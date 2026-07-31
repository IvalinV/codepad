<?php

/**
 * Drift guard for the grammars vendored by Task 13b.
 *
 * `resources/grammars/{csharp,ruby}.json` are copies of Phiki's bundled
 * grammars with a handful of regexes rewritten, because oniguruma refuses to
 * compile the originals. Those copies permanently shadow whatever upstream
 * ships, so a routine `composer update` would otherwise pin the patched
 * languages to the Phiki version they were copied from — silently, and with a
 * green suite.
 *
 * This test re-derives each vendored copy from the *current* upstream file and
 * asserts the result is byte-identical, so upstream moving is loud rather than
 * invisible.
 */

/**
 * The complete patch, as the single source of truth for what Task 13b changed.
 *
 * Patterns are written as they appear in the grammar (not as JSON-escaped
 * bytes); `rawJsonBytes()` derives the on-disk form.
 *
 * @return array<string, list<array{site: string, before: string, after: string, occurrences: int, reason: string}>>
 */
function vendoredGrammarPatch(): array
{
    return [
        'csharp' => [
            [
                'site' => 'repository.preprocessor.end',
                'before' => '(?<=$)',
                'after' => '$',
                'occurrences' => 1,
                'reason' => 'oniguruma rejects an anchor inside a look-behind, so the preprocessor block never closed',
            ],
            [
                'site' => 'repository.await-expression.match and repository.await-statement.begin',
                'before' => '(?<!\.\s*)\b(await)\b',
                'after' => '\b(await)\b',
                'occurrences' => 2,
                'reason' => 'oniguruma rejects the variable-length look-behind `\s*`',
            ],
        ],
        'ruby' => [
            [
                'site' => 'patterns[106].begin',
                'before' => '(?<={|{\s+|[^A-Za-z0-9_:@$]do|^do|[^A-Za-z0-9_:@$]do\s+|^do\s+)(\|)',
                'after' => '(?<={|{\s|[^A-Za-z0-9_:@$]do|^do|[^A-Za-z0-9_:@$]do\s|^do\s)(\|)',
                'occurrences' => 1,
                'reason' => 'oniguruma rejects the variable-length quantifier `\s+` inside a look-behind; pinning each run to one character also keeps the look-behind from matching the binary-or operator',
            ],
        ],
    ];
}

/** Encodes a regex to the exact bytes it occupies inside the minified grammar JSON. */
function rawJsonBytes(string $pattern): string
{
    return substr(json_encode($pattern, JSON_UNESCAPED_SLASHES), 1, -1);
}

/** Byte offset of the first difference, or null when the strings are identical. */
function firstByteDifference(string $expected, string $actual): ?int
{
    $shared = min(strlen($expected), strlen($actual));

    for ($offset = 0; $offset < $shared; $offset++) {
        if ($expected[$offset] !== $actual[$offset]) {
            return $offset;
        }
    }

    return strlen($expected) === strlen($actual) ? null : $shared;
}

it('vendors nothing but the documented patch on top of the current upstream grammar', function (string $grammar): void {
    $upstream = base_path("vendor/phiki/phiki/resources/grammars/{$grammar}.json");
    $vendored = resource_path("grammars/{$grammar}.json");

    expect(is_file($upstream))->toBeTrue("Upstream grammar is missing: {$upstream}");
    expect(is_file($vendored))->toBeTrue(
        "The patched grammar {$vendored} is missing, but AppServiceProvider still registers it. ".
        'Highlighting for this language will fail at runtime. Restore it by re-deriving it from '.
        'the upstream copy using the replacements in vendoredGrammarPatch().'
    );

    $rederived = file_get_contents($upstream);

    foreach (vendoredGrammarPatch()[$grammar] as $replacement) {
        $before = rawJsonBytes($replacement['before']);
        $found = substr_count($rederived, $before);

        expect($found)->toBe($replacement['occurrences'], sprintf(
            "PATCH NO LONGER APPLIES to %s.json at %s.\n\n".
            "Expected to find the broken pattern %d time(s) in the current upstream grammar, found %d:\n".
            "    %s\n\n".
            "Reason it was patched: %s\n\n".
            "Upstream has most likely rewritten or removed this pattern. Check whether phiki now ships a\n".
            "grammar oniguruma accepts — if it does, DELETE resources/grammars/%s.json, drop '%s' from\n".
            "AppServiceProvider::PATCHED_GRAMMARS, and remove its entry here. The vendoring exists only\n".
            'while upstream is broken.',
            $grammar,
            $replacement['site'],
            $replacement['occurrences'],
            $found,
            $replacement['before'],
            $replacement['reason'],
            $grammar,
            $grammar,
        ));

        $rederived = str_replace($before, rawJsonBytes($replacement['after']), $rederived);
    }

    $actual = file_get_contents($vendored);
    $offset = firstByteDifference($rederived, $actual);

    expect($offset)->toBeNull(sprintf(
        "resources/grammars/%s.json HAS DRIFTED from upstream-plus-patch.\n\n".
        "First difference at byte %d of %d (expected) / %d (actual).\n".
        "    expected: ...%s...\n".
        "    actual:   ...%s...\n\n".
        "Either phiki's bundled %s.json changed (a composer update), or the vendored copy was edited by\n".
        "hand. Fix by re-deriving the vendored copy from the NEW upstream file — copy it, re-apply the\n".
        "replacements in vendoredGrammarPatch(), and keep the file minified on one line. While doing so,\n".
        're-check whether the patch is still needed at all: if upstream fixed these patterns, drop the '.
        'vendoring entirely rather than carrying it forward.',
        $grammar,
        $offset ?? 0,
        strlen($rederived),
        strlen($actual),
        substr($rederived, max(0, ($offset ?? 0) - 60), 140),
        substr($actual, max(0, ($offset ?? 0) - 60), 140),
        $grammar,
    ));
})->with(['csharp', 'ruby']);
