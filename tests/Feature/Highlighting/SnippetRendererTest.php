<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Models\Snippet;
use App\Support\Highlighting\SnippetRenderer;

beforeEach(function () {
    $this->renderer = app(SnippetRenderer::class);
});

it('creates one render per theme', function () {
    $snippet = Snippet::factory()->create();

    $this->renderer->refresh($snippet);

    expect($snippet->renders()->count())->toBe(2);
});

it('returns a render for a fresh snippet', function () {
    $snippet = Snippet::factory()->create();
    $this->renderer->refresh($snippet);

    expect($this->renderer->renderFor($snippet->fresh(), ThemeVariant::Light))->not->toBeNull();
});

it('returns null when no render exists yet', function () {
    expect($this->renderer->renderFor(Snippet::factory()->create(), ThemeVariant::Light))->toBeNull();
});

it('returns null once the body has changed', function () {
    $snippet = Snippet::factory()->create();
    $this->renderer->refresh($snippet);

    $snippet->update(['body' => '<?php echo "different";']);

    expect($this->renderer->renderFor($snippet->fresh(), ThemeVariant::Light))->toBeNull();
});

it('returns null once the language has changed', function () {
    $snippet = Snippet::factory()->create(['language' => Language::Php]);
    $this->renderer->refresh($snippet);

    $snippet->update(['language' => Language::Python]);

    expect($this->renderer->renderFor($snippet->fresh(), ThemeVariant::Light))->toBeNull();
});

it('replaces rather than duplicates renders on repeated refresh', function () {
    $snippet = Snippet::factory()->create();

    $this->renderer->refresh($snippet);
    $snippet->update(['body' => '<?php echo "changed";']);
    $this->renderer->refresh($snippet->fresh());

    expect($snippet->renders()->count())->toBe(2)
        ->and($this->renderer->renderFor($snippet->fresh(), ThemeVariant::Dark))->not->toBeNull();
});
