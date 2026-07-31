<?php

use App\Enums\Language;
use App\Models\Snippet;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

it('preserves existing rows when migrations are re-run', function () {
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
    /**
     * Only the body of `up()` is scanned. A `down()` that drops what its own
     * `up()` created is standard Laravel scaffolding and must not be flagged
     * — both existing create-migrations correctly do this. What must never
     * happen is a migration's `up()` — the method that runs on every app
     * start — destroying a data table.
     */
    $upBody = function (string $contents): string {
        $upStart = strpos($contents, 'function up(');

        if ($upStart === false) {
            return '';
        }

        $downStart = strpos($contents, 'function down(', $upStart);

        return $downStart === false
            ? substr($contents, $upStart)
            : substr($contents, $upStart, $downStart - $upStart);
    };

    $offenders = collect(File::files(database_path('migrations')))
        ->filter(function (SplFileInfo $file) use ($upBody): bool {
            $body = $upBody(file_get_contents($file->getPathname()));

            return str_contains($body, "dropIfExists('snippets'")
                || str_contains($body, "dropIfExists('snippet_renders'")
                || str_contains($body, 'truncate(');
        })
        ->map(fn (SplFileInfo $file): string => $file->getFilename())
        ->values();

    expect($offenders)->toBeEmpty("Destructive migration(s) in up(): {$offenders->implode(', ')}");
});
