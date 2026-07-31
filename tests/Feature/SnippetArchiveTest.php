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
