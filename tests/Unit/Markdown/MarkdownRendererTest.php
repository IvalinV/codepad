<?php

use App\Support\Markdown\MarkdownRenderer;

it('turns markdown headings into native display blocks with their levels', function () {
    $blocks = app(MarkdownRenderer::class)->render("# Heading\n\n## Heading 2\n\n### Heading 3");

    expect($blocks)->toHaveCount(3)
        ->and($blocks[0])->toBe(['type' => 'heading', 'level' => 1, 'text' => 'Heading'])
        ->and($blocks[1])->toBe(['type' => 'heading', 'level' => 2, 'text' => 'Heading 2'])
        ->and($blocks[2])->toBe(['type' => 'heading', 'level' => 3, 'text' => 'Heading 3']);
});

it('keeps paragraphs as separate markdown display blocks', function () {
    $blocks = app(MarkdownRenderer::class)->render("First paragraph.\n\nSecond paragraph.");

    expect($blocks)->toBe([
        ['type' => 'paragraph', 'text' => 'First paragraph.'],
        ['type' => 'paragraph', 'text' => 'Second paragraph.'],
    ]);
});

it('turns lists and fenced code into distinct display blocks', function () {
    $blocks = app(MarkdownRenderer::class)->render("- First\n- Second\n\n```php\necho 1;\n```");

    expect($blocks)->toBe([
        [
            'type' => 'list',
            'ordered' => false,
            'items' => [['text' => 'First'], ['text' => 'Second']],
        ],
        [
            'type' => 'code',
            'language' => 'php',
            'text' => "echo 1;\n",
        ],
    ]);
});

it('turns pipe tables into header and body cell data', function () {
    $markdown = <<<'MARKDOWN'
| Тип интеграция | Цена за портал |
|---|---:|
| XML / RSS / Feed интеграция | 300 – 800 EUR |
| Стандартна API интеграция | 800 – 1 500 EUR |
| По-сложна API интеграция | 1 500 – 2 500 EUR |
| Browser automation | Индивидуална оферта |
MARKDOWN;

    $blocks = app(MarkdownRenderer::class)->render($markdown);

    expect($blocks)->toBe([
        [
            'type' => 'table',
            'headers' => [
                ['text' => 'Тип интеграция', 'align' => null],
                ['text' => 'Цена за портал', 'align' => 'right'],
            ],
            'rows' => [
                [
                    ['text' => 'XML / RSS / Feed интеграция', 'align' => null],
                    ['text' => '300 – 800 EUR', 'align' => 'right'],
                ],
                [
                    ['text' => 'Стандартна API интеграция', 'align' => null],
                    ['text' => '800 – 1 500 EUR', 'align' => 'right'],
                ],
                [
                    ['text' => 'По-сложна API интеграция', 'align' => null],
                    ['text' => '1 500 – 2 500 EUR', 'align' => 'right'],
                ],
                [
                    ['text' => 'Browser automation', 'align' => null],
                    ['text' => 'Индивидуална оферта', 'align' => 'right'],
                ],
            ],
        ],
    ]);
});
