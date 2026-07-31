<?php

namespace App\Support\Highlighting;

final class HighlightedCode
{
    /** @param  array<int, array<int, array{text: string, color: string}>>  $lines */
    public function __construct(public readonly array $lines) {}

    /** @param  array<int, array<int, array{text: string, color: string}>>  $lines */
    public static function fromArray(array $lines): self
    {
        return new self($lines);
    }

    /** @return array<int, array<int, array{text: string, color: string}>> */
    public function toArray(): array
    {
        return $this->lines;
    }

    public function lineCount(): int
    {
        return count($this->lines);
    }

    public function truncate(int $maxLines): self
    {
        return new self(array_slice($this->lines, 0, $maxLines));
    }
}
