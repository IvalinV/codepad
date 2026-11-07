<?php

namespace App\Support\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Parser\MarkdownParser;

final class MarkdownRenderer
{
    /** @return list<array<string, mixed>> */
    public function render(string $markdown): array
    {
        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);

        $document = (new MarkdownParser($environment))->parse($markdown);
        $blocks = [];

        foreach ($document->children() as $block) {
            if (! $block instanceof AbstractBlock) {
                continue;
            }

            $blocks[] = $this->renderBlock($block);
        }

        return $blocks;
    }

    /** @return array<string, mixed> */
    private function renderBlock(AbstractBlock $block): array
    {
        if ($block instanceof Heading) {
            return [
                'type' => 'heading',
                'level' => $block->getLevel(),
                'text' => $this->textContent($block),
            ];
        }

        if ($block instanceof FencedCode) {
            return [
                'type' => 'code',
                'language' => $block->getInfoWords()[0] ?? '',
                'text' => $block->getLiteral(),
            ];
        }

        if ($block instanceof ListBlock) {
            return [
                'type' => 'list',
                'ordered' => $block->getListData()->type === ListBlock::TYPE_ORDERED,
                'items' => array_map(
                    fn (Node $item): array => ['text' => $this->textContent($item)],
                    iterator_to_array($block->children()),
                ),
            ];
        }

        if ($block instanceof Table) {
            $headers = [];
            $rows = [];

            foreach ($block->children() as $section) {
                if (! $section instanceof TableSection) {
                    continue;
                }

                foreach ($section->children() as $row) {
                    $cells = array_map(
                        fn (Node $cell): array => $this->renderTableCell($cell),
                        iterator_to_array($row->children()),
                    );

                    if ($section->isHead()) {
                        $headers = $cells;
                    } else {
                        $rows[] = $cells;
                    }
                }
            }

            return [
                'type' => 'table',
                'headers' => $headers,
                'rows' => $rows,
            ];
        }

        return [
            'type' => 'paragraph',
            'text' => $this->textContent($block),
        ];
    }

    private function textContent(Node $node): string
    {
        if ($node instanceof StringContainerInterface) {
            return $node->getLiteral();
        }

        $text = '';

        foreach ($node->children() as $child) {
            $text .= $this->textContent($child);
        }

        return $text;
    }

    /** @return array{text: string, align: string|null} */
    private function renderTableCell(Node $cell): array
    {
        if (! $cell instanceof TableCell) {
            return ['text' => $this->textContent($cell), 'align' => null];
        }

        return [
            'text' => $this->textContent($cell),
            'align' => $cell->getAlign(),
        ];
    }
}
