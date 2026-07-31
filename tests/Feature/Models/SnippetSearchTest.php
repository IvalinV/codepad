<?php

use App\Enums\Language;
use App\Models\Snippet;

it('matches on the body', function () {
    Snippet::factory()->create(['title' => 'A', 'body' => 'array_map($fn, $items)']);
    Snippet::factory()->create(['title' => 'B', 'body' => 'echo "nope";']);

    expect(Snippet::query()->search('array_map')->pluck('title')->all())->toBe(['A']);
});

it('matches on the title', function () {
    Snippet::factory()->create(['title' => 'Retry helper', 'body' => 'x']);
    Snippet::factory()->create(['title' => 'Other', 'body' => 'y']);

    expect(Snippet::query()->search('retry')->pluck('title')->all())->toBe(['Retry helper']);
});

it('is case insensitive', function () {
    Snippet::factory()->create(['title' => 'Retry helper', 'body' => 'x']);

    expect(Snippet::query()->search('RETRY')->count())->toBe(1);
});

it('escapes wildcards so they match literally', function () {
    Snippet::factory()->create(['title' => 'literal', 'body' => 'SELECT 100%']);
    Snippet::factory()->create(['title' => 'other', 'body' => 'SELECT 1']);

    expect(Snippet::query()->search('100%')->pluck('title')->all())->toBe(['literal']);
});

it('returns everything for a blank term', function () {
    Snippet::factory()->count(3)->create();

    expect(Snippet::query()->search(null)->count())->toBe(3)
        ->and(Snippet::query()->search('  ')->count())->toBe(3);
});

it('filters by language', function () {
    Snippet::factory()->create(['language' => Language::Php]);
    Snippet::factory()->create(['language' => Language::Python]);

    expect(Snippet::query()->forLanguage(Language::Php)->count())->toBe(1)
        ->and(Snippet::query()->forLanguage(null)->count())->toBe(2);
});

it('orders by most recently updated', function () {
    $old = Snippet::factory()->create(['title' => 'old']);
    $new = Snippet::factory()->create(['title' => 'new']);

    $old->forceFill(['updated_at' => now()->subDay()])->save();

    expect(Snippet::query()->recent()->pluck('title')->all())->toBe(['new', 'old']);
});
