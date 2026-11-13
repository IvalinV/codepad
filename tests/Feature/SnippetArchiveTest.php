<?php

use App\Enums\Language;
use App\Models\Snippet;
use App\Support\SnippetArchive;

beforeEach(function () {
    $this->archive = app(SnippetArchive::class);
});

it('exports every snippet as json', function () {
    Snippet::factory()->create(['title' => 'One', 'body' => 'a', 'language' => Language::Php]);
    Snippet::factory()->create(['title' => 'Two', 'body' => 'b', 'language' => Language::Go]);

    $decoded = json_decode($this->archive->export(), true);

    expect($decoded['version'])->toBe(1)
        ->and($decoded['snippets'])->toHaveCount(2)
        ->and($decoded['snippets'][0])->toHaveKeys(['title', 'body', 'language', 'created_at', 'updated_at']);
});

it('does not export derived renders', function () {
    $snippet = Snippet::factory()->create();
    $snippet->renders()->create(['theme' => 'light', 'content' => [], 'hash' => 'x']);

    expect($this->archive->export())->not->toContain('renders');
});

it('round-trips an export back into an empty database', function () {
    Snippet::factory()->create(['title' => 'Kept', 'body' => 'keep me', 'language' => Language::Rust]);
    $json = $this->archive->export();

    Snippet::query()->delete();
    $imported = $this->archive->import($json);

    expect($imported)->toBe(1)
        ->and(Snippet::query()->first()->title)->toBe('Kept')
        ->and(Snippet::query()->first()->language)->toBe(Language::Rust);
});

it('preserves exported timestamps when importing', function () {
    $createdAt = '2024-01-02T03:04:05+00:00';
    $updatedAt = '2024-02-03T04:05:06+00:00';
    $json = json_encode(['version' => 1, 'snippets' => [[
        'title' => 'Timed',
        'body' => 'body',
        'language' => 'php',
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ]]]);

    $this->archive->import($json);

    expect(Snippet::query()->sole()->created_at?->toIso8601String())->toBe($createdAt)
        ->and(Snippet::query()->sole()->updated_at?->toIso8601String())->toBe($updatedAt);
});

it('ignores malformed timestamps without changing the imported body', function () {
    $json = json_encode(['version' => 1, 'snippets' => [[
        'title' => 'Timed',
        'body' => 'body',
        'language' => 'php',
        'created_at' => '2024-02-30T03:04:05+00:00',
        'updated_at' => 123,
    ]]]);

    $this->archive->import($json);

    expect(Snippet::query()->sole()->body)->toBe('body')
        ->and(Snippet::query()->sole()->created_at?->toDateString())->not->toBe('2024-03-01');
});

it('adds to existing snippets rather than replacing them', function () {
    Snippet::factory()->create(['title' => 'Existing']);
    $json = json_encode(['version' => 1, 'snippets' => [
        ['title' => 'Incoming', 'body' => 'x', 'language' => 'php', 'created_at' => null, 'updated_at' => null],
    ]]);

    $this->archive->import($json);

    expect(Snippet::query()->count())->toBe(2);
});

it('rejects malformed json', function () {
    expect(fn () => $this->archive->import('not json'))->toThrow(InvalidArgumentException::class);
});

it('rejects JSON with invalid UTF-8', function () {
    expect(fn () => $this->archive->import("{\"version\":1,\"snippets\":\xFF}"))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an object-shaped snippets collection', function () {
    expect(fn () => $this->archive->import(json_encode([
        'version' => 1,
        'snippets' => ['one' => ['title' => 'One', 'body' => 'body', 'language' => 'php']],
    ])))->toThrow(InvalidArgumentException::class);
});

it('rejects an unknown archive version', function () {
    expect(fn () => $this->archive->import(json_encode(['version' => 99, 'snippets' => []])))
        ->toThrow(InvalidArgumentException::class);
});

it('skips entries with an unknown language rather than aborting', function () {
    $json = json_encode(['version' => 1, 'snippets' => [
        ['title' => 'Good', 'body' => 'x', 'language' => 'php', 'created_at' => null, 'updated_at' => null],
        ['title' => 'Bad', 'body' => 'y', 'language' => 'cobol', 'created_at' => null, 'updated_at' => null],
    ]]);

    expect($this->archive->import($json))->toBe(1)
        ->and(Snippet::query()->count())->toBe(1);
});

it('skips a non-string body without aborting, wherever it appears', function (int $badIndex) {
    $goodEntry = fn (string $title): array => [
        'title' => $title, 'body' => 'good body', 'language' => 'php', 'created_at' => null, 'updated_at' => null,
    ];
    $badEntry = ['title' => 'Bad', 'body' => ['nested' => 'value'], 'language' => 'php', 'created_at' => null, 'updated_at' => null];

    $entries = [$goodEntry('First'), $goodEntry('Second'), $goodEntry('Third')];
    array_splice($entries, $badIndex, 0, [$badEntry]);

    $json = json_encode(['version' => 1, 'snippets' => $entries]);

    expect($this->archive->import($json))->toBe(3)
        ->and(Snippet::query()->count())->toBe(3)
        ->and(Snippet::query()->pluck('title')->all())->toBe(['First', 'Second', 'Third']);
})->with([
    'first' => [0],
    'middle' => [1],
    'last' => [3],
]);

it('skips a scalar body rather than silently stringifying it', function (mixed $body) {
    $json = json_encode(['version' => 1, 'snippets' => [
        ['title' => 'Good', 'body' => 'kept', 'language' => 'php', 'created_at' => null, 'updated_at' => null],
        ['title' => 'Bad', 'body' => $body, 'language' => 'php', 'created_at' => null, 'updated_at' => null],
    ]]);

    expect($this->archive->import($json))->toBe(1)
        ->and(Snippet::query()->count())->toBe(1)
        ->and(Snippet::query()->first()->title)->toBe('Good');
})->with([
    'integer' => [12345],
    'boolean' => [true],
]);

it('skips an entry with a non-string title', function () {
    $json = json_encode(['version' => 1, 'snippets' => [
        ['title' => 'Good', 'body' => 'kept', 'language' => 'php', 'created_at' => null, 'updated_at' => null],
        ['title' => ['not', 'a', 'string'], 'body' => 'y', 'language' => 'php', 'created_at' => null, 'updated_at' => null],
    ]]);

    expect($this->archive->import($json))->toBe(1)
        ->and(Snippet::query()->count())->toBe(1);
});

it('skips an entry with a non-string language', function () {
    $json = json_encode(['version' => 1, 'snippets' => [
        ['title' => 'Good', 'body' => 'kept', 'language' => 'php', 'created_at' => null, 'updated_at' => null],
        ['title' => 'Bad', 'body' => 'y', 'language' => ['php'], 'created_at' => null, 'updated_at' => null],
    ]]);

    expect($this->archive->import($json))->toBe(1)
        ->and(Snippet::query()->count())->toBe(1);
});

it('skips empty bodies and overlong titles without aborting valid entries', function () {
    $json = json_encode(['version' => 1, 'snippets' => [
        ['title' => 'Empty', 'body' => '', 'language' => 'php'],
        ['title' => str_repeat('x', 256), 'body' => 'body', 'language' => 'php'],
        ['title' => 'Good', 'body' => 'body', 'language' => 'php'],
    ]]);

    expect($this->archive->import($json))->toBe(1)
        ->and(Snippet::query()->sole()->title)->toBe('Good');
});

it('imports a body at exactly the 100 KB cap', function () {
    $body = str_repeat('a', 102400);
    $json = json_encode(['version' => 1, 'snippets' => [
        ['title' => 'Big', 'body' => $body, 'language' => 'php', 'created_at' => null, 'updated_at' => null],
    ]]);

    expect($this->archive->import($json))->toBe(1)
        ->and(Snippet::query()->first()->body)->toHaveLength(102400);
});

it('skips a body over the 100 KB cap without aborting the rest of the import', function () {
    $tooLarge = str_repeat('a', 102401);
    $json = json_encode(['version' => 1, 'snippets' => [
        ['title' => 'Good', 'body' => 'kept', 'language' => 'php', 'created_at' => null, 'updated_at' => null],
        ['title' => 'TooBig', 'body' => $tooLarge, 'language' => 'php', 'created_at' => null, 'updated_at' => null],
    ]]);

    expect($this->archive->import($json))->toBe(1)
        ->and(Snippet::query()->count())->toBe(1)
        ->and(Snippet::query()->first()->title)->toBe('Good');
});

it('rejects JSON that exceeds the decoder nesting limit', function () {
    $nested = [];

    for ($depth = 0; $depth < 513; $depth++) {
        $nested = ['nested' => $nested];
    }

    $json = json_encode($nested, JSON_THROW_ON_ERROR, 1024);

    expect(fn () => $this->archive->import($json))->toThrow(InvalidArgumentException::class);
});
