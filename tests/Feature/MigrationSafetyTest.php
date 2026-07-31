<?php

use App\Enums\Language;
use App\Models\Snippet;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * RefreshDatabase has already applied every migration in
 * `database/migrations/` before this test body runs, so the explicit
 * `migrate` call below finds nothing new to apply — it is a no-op, because
 * every migration is already recorded in the `migrations` table. What this
 * actually proves is narrower than "safe to re-run migrate against a
 * populated database": it proves the `snippets` table is queryable and
 * holds the inserted row once the full migration set has applied, and that
 * invoking `migrate` again (as NativePHP does on every app start) does not
 * disturb that row when there is nothing left to run. It is kept as a
 * belt-and-braces functional check alongside the static scan below, which
 * is what actually catches a destructive `up()`.
 */
it('leaves an inserted row intact after the full migration set has applied', function () {
    $snippet = Snippet::query()->create([
        'title' => 'Survivor',
        'body' => '<?php echo "still here";',
        'language' => Language::Php,
    ]);

    Artisan::call('migrate', ['--force' => true]);

    expect(Snippet::query()->count())->toBe(1)
        ->and(Snippet::query()->find($snippet->id)?->title)->toBe('Survivor');
});

it('has no migration whose up() method drops or truncates a data table', function () {
    $offenders = collect(File::files(database_path('migrations')))
        ->filter(fn (SplFileInfo $file): bool => migrationUpIsDestructive(file_get_contents($file->getPathname())))
        ->map(fn (SplFileInfo $file): string => $file->getFilename())
        ->values();

    expect($offenders)->toBeEmpty("Destructive migration(s) in up(): {$offenders->implode(', ')}");
});

/**
 * Whether a migration file's `up()` method destroys the `snippets` or
 * `snippet_renders` data tables.
 *
 * Only `up()` is inspected — that is the method NativePHP runs on every
 * app start, whereas a `down()` that reverses its own `up()` is correct
 * scaffolding and must never trip this guard.
 *
 * Two independent signals are checked, both scoped to the token stream of
 * `up()` alone (comments stripped, so a comment mentioning `dropIfExists`
 * or `function down()` cannot fool it):
 *
 * - A Schema/query-builder call named `dropIfExists`, `drop`, `truncate`,
 *   or `delete` whose surrounding statement also references `snippets` or
 *   `snippet_renders`, regardless of quote style or line breaks.
 * - A raw SQL string literal — `DELETE FROM`, `DROP TABLE`, or `TRUNCATE`
 *   followed by one of those table names, matched case-insensitively.
 */
function migrationUpIsDestructive(string $contents): bool
{
    $upBodyTokens = migrationUpMethodTokens(token_get_all($contents));

    if ($upBodyTokens === []) {
        return false;
    }

    $upBodyText = migrationTokensToText($upBodyTokens);

    return migrationCallsDestroyDataTable($upBodyText) || migrationRawSqlDestroysDataTable($upBodyText);
}

/**
 * Locates the class method literally named `up` via its `T_FUNCTION`/
 * `T_STRING` token pair, then isolates its body by counting brace depth
 * over the token stream from the method's opening `{` to its matching
 * closing `}`. Using brace matching — rather than a textual search for
 * `function down(` — means a comment inside `up()` that happens to contain
 * that literal text cannot truncate the extraction early.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 * @return array<int, array{0: int, 1: string, 2: int}|string>
 */
function migrationUpMethodTokens(array $tokens): array
{
    $count = count($tokens);
    $upNameIndex = null;

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (! is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }

        for ($j = $i + 1; $j < $count; $j++) {
            $next = $tokens[$j];

            if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (is_array($next) && $next[0] === T_STRING && $next[1] === 'up') {
                $upNameIndex = $j;
            }

            break;
        }

        if ($upNameIndex !== null) {
            break;
        }
    }

    if ($upNameIndex === null) {
        return [];
    }

    $bodyStart = null;

    for ($i = $upNameIndex; $i < $count; $i++) {
        if ($tokens[$i] === '{') {
            $bodyStart = $i + 1;
            break;
        }
    }

    if ($bodyStart === null) {
        return [];
    }

    $depth = 1;
    $body = [];

    for ($i = $bodyStart; $i < $count; $i++) {
        $token = $tokens[$i];

        if ($token === '{') {
            $depth++;
        } elseif ($token === '}') {
            $depth--;

            if ($depth === 0) {
                break;
            }
        }

        $body[] = $token;
    }

    return $body;
}

/**
 * Reconstructs source text from a token list, dropping comment tokens so
 * their content can never be mistaken for real code.
 *
 * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
 */
function migrationTokensToText(array $tokens): string
{
    $text = '';

    foreach ($tokens as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $text .= $token[1];

            continue;
        }

        $text .= $token;
    }

    return $text;
}

/**
 * Whether the comment-stripped `up()` body contains a `dropIfExists`,
 * `drop`, `truncate`, or `delete` call in the same statement as a
 * reference to `snippets` or `snippet_renders`. Splitting on `;` scopes
 * the table-name check to the statement making the call — e.g.
 * `DB::table('snippets')->delete();` — rather than the whole method body.
 */
function migrationCallsDestroyDataTable(string $upBodyText): bool
{
    foreach (explode(';', $upBodyText) as $statement) {
        if (! preg_match('/\b(dropIfExists|drop|truncate|delete)\s*\(/i', $statement)) {
            continue;
        }

        foreach (['snippets', 'snippet_renders'] as $table) {
            if (str_contains($statement, $table)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Whether the comment-stripped `up()` body contains a raw destructive SQL
 * string literal — `DELETE FROM`, `DROP TABLE`, or `TRUNCATE [TABLE]` —
 * targeting `snippets` or `snippet_renders`. Quote style is irrelevant
 * because the check runs against the reconstructed text, which still
 * contains the literal quote characters around the table name.
 */
function migrationRawSqlDestroysDataTable(string $upBodyText): bool
{
    $pattern = '/\b(delete\s+from|drop\s+table|truncate\s+table|truncate)\s+[`\'"]?(snippets|snippet_renders)\b/i';

    return (bool) preg_match($pattern, $upBodyText);
}
