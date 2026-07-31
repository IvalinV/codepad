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

/*
| The body is validated as `['required','string','max:102400']`, which says
| nothing about encoding, so an ordinary paste of Latin-1 text reaches
| refresh() as invalid UTF-8. Highlighting it must degrade rather than throw:
| refresh() runs synchronously on save, so a throw here costs the user the
| snippet they just captured.
*/
it('still persists both renders for a body that is not valid UTF-8', function () {
    $snippet = Snippet::factory()->create(['body' => "caf\xE9"]);

    $this->renderer->refresh($snippet);

    expect($snippet->renders()->count())->toBe(2)
        ->and($this->renderer->renderFor($snippet->fresh(), ThemeVariant::Light))->not->toBeNull();
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
