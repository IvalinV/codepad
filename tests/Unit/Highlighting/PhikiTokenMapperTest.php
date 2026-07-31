<?php

use App\Support\Highlighting\HighlightedCode;
use App\Support\Highlighting\PhikiTokenMapper;
use Phiki\Grammar\Grammar;
use Phiki\Phiki;
use Phiki\Theme\Theme;

beforeEach(function () {
    $this->source = "<?php\n\nfunction handle(): void\n{\n    echo 'hi';\n}";
    $this->tokens = (new Phiki)->codeToHighlightedTokens($this->source, Grammar::Php, Theme::GithubLight);
    $this->mapper = new PhikiTokenMapper;
});

it('returns a HighlightedCode value object', function () {
    expect($this->mapper->map($this->tokens))->toBeInstanceOf(HighlightedCode::class);
});

it('preserves the source line count', function () {
    expect($this->mapper->map($this->tokens)->lineCount())->toBe(6);
});

it('assigns a hex colour to every run', function () {
    foreach ($this->mapper->map($this->tokens)->toArray() as $line) {
        foreach ($line as $run) {
            expect($run['color'])->toMatch('/^#[0-9a-f]{3,8}$/i');
        }
    }
});

it('reconstructs the original source when runs are concatenated', function () {
    $text = collect($this->mapper->map($this->tokens)->toArray())
        ->map(fn (array $line): string => collect($line)->pluck('text')->implode(''))
        ->implode("\n");

    expect(trim($text))->toBe(trim($this->source));
});

it('round-trips through toArray and fromArray', function () {
    $mapped = $this->mapper->map($this->tokens);

    expect(HighlightedCode::fromArray($mapped->toArray())->toArray())->toBe($mapped->toArray());
});

it('truncates to a maximum number of lines', function () {
    expect($this->mapper->map($this->tokens)->truncate(3)->lineCount())->toBe(3);
});

it('leaves shorter input untouched when truncating', function () {
    expect($this->mapper->map($this->tokens)->truncate(100)->lineCount())->toBe(6);
});
