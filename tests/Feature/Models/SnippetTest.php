<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Models\Snippet;
use App\Models\SnippetRender;
use Illuminate\Database\UniqueConstraintViolationException;

it('stores a snippet with a nullable title', function () {
    $snippet = Snippet::factory()->create(['title' => null, 'body' => '<?php echo 1;']);

    expect($snippet->fresh()->title)->toBeNull()
        ->and($snippet->fresh()->body)->toBe('<?php echo 1;');
});

it('casts language to the enum', function () {
    $snippet = Snippet::factory()->create(['language' => Language::Php]);

    expect($snippet->fresh()->language)->toBe(Language::Php);
});

it('has many renders', function () {
    $snippet = Snippet::factory()->create();

    $snippet->renders()->create([
        'theme' => ThemeVariant::Light,
        'content' => [[['text' => 'echo', 'color' => '#d73a49']]],
        'hash' => 'abc123',
    ]);

    expect($snippet->renders)->toHaveCount(1)
        ->and($snippet->renders->first()->theme)->toBe(ThemeVariant::Light)
        ->and($snippet->renders->first()->content)->toBeArray();
});

it('deletes renders when the snippet is deleted', function () {
    $snippet = Snippet::factory()->create();
    $snippet->renders()->create([
        'theme' => ThemeVariant::Dark,
        'content' => [],
        'hash' => 'abc123',
    ]);

    $snippet->delete();

    expect(SnippetRender::query()->count())->toBe(0);
});

it('permits only one render per snippet and theme', function () {
    $snippet = Snippet::factory()->create();
    $attributes = ['theme' => ThemeVariant::Light, 'content' => [], 'hash' => 'x'];

    $snippet->renders()->create($attributes);

    expect(fn () => $snippet->renders()->create($attributes))
        ->toThrow(UniqueConstraintViolationException::class);
});
