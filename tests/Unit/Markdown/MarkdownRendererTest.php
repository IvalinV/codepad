<?php

use App\Support\Markdown\MarkdownRenderer;

it('turns markdown headings into native display blocks with their levels', function () {
    $blocks = app(MarkdownRenderer::class)->render("# Heading\n\n## Heading 2\n\n### Heading 3");

    expect($blocks)->toHaveCount(3)
        ->and($blocks[0])->toBe([
            'type' => 'heading',
            'level' => 1,
            'text' => 'Heading',
            'segments' => [['type' => 'text', 'text' => 'Heading']],
        ])
        ->and($blocks[1])->toBe([
            'type' => 'heading',
            'level' => 2,
            'text' => 'Heading 2',
            'segments' => [['type' => 'text', 'text' => 'Heading 2']],
        ])
        ->and($blocks[2])->toBe([
            'type' => 'heading',
            'level' => 3,
            'text' => 'Heading 3',
            'segments' => [['type' => 'text', 'text' => 'Heading 3']],
        ]);
});

it('keeps paragraphs as separate markdown display blocks', function () {
    $blocks = app(MarkdownRenderer::class)->render("First paragraph.\n\nSecond paragraph.");

    expect($blocks)->toBe([
        [
            'type' => 'paragraph',
            'text' => 'First paragraph.',
            'segments' => [['type' => 'text', 'text' => 'First paragraph.']],
        ],
        [
            'type' => 'paragraph',
            'text' => 'Second paragraph.',
            'segments' => [['type' => 'text', 'text' => 'Second paragraph.']],
        ],
    ]);
});

it('turns lists and fenced code into distinct display blocks', function () {
    $blocks = app(MarkdownRenderer::class)->render("- First\n- Second\n\n```php\necho 1;\n```");

    expect($blocks)->toBe([
        [
            'type' => 'list',
            'ordered' => false,
            'items' => [
                ['text' => 'First', 'segments' => [['type' => 'text', 'text' => 'First']]],
                ['text' => 'Second', 'segments' => [['type' => 'text', 'text' => 'Second']]],
            ],
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
                [
                    'text' => 'Тип интеграция',
                    'align' => null,
                    'segments' => [['type' => 'text', 'text' => 'Тип интеграция']],
                ],
                [
                    'text' => 'Цена за портал',
                    'align' => 'right',
                    'segments' => [['type' => 'text', 'text' => 'Цена за портал']],
                ],
            ],
            'rows' => [
                [
                    [
                        'text' => 'XML / RSS / Feed интеграция',
                        'align' => null,
                        'segments' => [['type' => 'text', 'text' => 'XML / RSS / Feed интеграция']],
                    ],
                    [
                        'text' => '300 – 800 EUR',
                        'align' => 'right',
                        'segments' => [['type' => 'text', 'text' => '300 – 800 EUR']],
                    ],
                ],
                [
                    [
                        'text' => 'Стандартна API интеграция',
                        'align' => null,
                        'segments' => [['type' => 'text', 'text' => 'Стандартна API интеграция']],
                    ],
                    [
                        'text' => '800 – 1 500 EUR',
                        'align' => 'right',
                        'segments' => [['type' => 'text', 'text' => '800 – 1 500 EUR']],
                    ],
                ],
                [
                    [
                        'text' => 'По-сложна API интеграция',
                        'align' => null,
                        'segments' => [['type' => 'text', 'text' => 'По-сложна API интеграция']],
                    ],
                    [
                        'text' => '1 500 – 2 500 EUR',
                        'align' => 'right',
                        'segments' => [['type' => 'text', 'text' => '1 500 – 2 500 EUR']],
                    ],
                ],
                [
                    [
                        'text' => 'Browser automation',
                        'align' => null,
                        'segments' => [['type' => 'text', 'text' => 'Browser automation']],
                    ],
                    [
                        'text' => 'Индивидуална оферта',
                        'align' => 'right',
                        'segments' => [['type' => 'text', 'text' => 'Индивидуална оферта']],
                    ],
                ],
            ],
        ],
    ]);
});

it('exposes markdown and bare urls as separate safe link segments', function () {
    $blocks = app(MarkdownRenderer::class)->render(
        'Read [Laravel](https://laravel.com) and https://example.com/docs.'
    );

    expect($blocks[0]['segments'])->toBe([
        ['type' => 'text', 'text' => 'Read '],
        ['type' => 'link', 'text' => 'Laravel', 'url' => 'https://laravel.com'],
        ['type' => 'text', 'text' => ' and '],
        ['type' => 'link', 'text' => 'https://example.com/docs', 'url' => 'https://example.com/docs'],
        ['type' => 'text', 'text' => '.'],
    ]);
});

it('detects links in headings lists and table cells', function () {
    $blocks = app(MarkdownRenderer::class)->render(<<<'MARKDOWN'
# [Heading](https://heading.test)

- https://list.test/item

| Link |
| --- |
| [Cell](https://cell.test) |
MARKDOWN);

    expect($blocks[0]['segments'][0])->toBe([
        'type' => 'link',
        'text' => 'Heading',
        'url' => 'https://heading.test',
    ])
        ->and($blocks[1]['items'][0]['segments'])->toBe([
            ['type' => 'link', 'text' => 'https://list.test/item', 'url' => 'https://list.test/item'],
        ])
        ->and($blocks[2]['rows'][0][0]['segments'])->toBe([
            ['type' => 'link', 'text' => 'Cell', 'url' => 'https://cell.test'],
        ]);
});

it('keeps fenced code urls literal', function () {
    $blocks = app(MarkdownRenderer::class)->render("```\nhttps://example.com\n```");

    expect($blocks[0])->toBe([
        'type' => 'code',
        'language' => '',
        'text' => "https://example.com\n",
    ])->not->toHaveKey('segments');
});

it('keeps unsupported schemes as ordinary markdown text', function () {
    $blocks = app(MarkdownRenderer::class)->render('javascript:alert(1) file:///tmp/test mailto:test@example.com');

    expect($blocks[0]['segments'])->toBe([
        ['type' => 'text', 'text' => 'javascript:alert(1) file:///tmp/test mailto:test@example.com'],
    ]);
});
