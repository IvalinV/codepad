<?php

namespace App\Support\PlainText;

use App\Support\Urls\UrlSegmenter;

final class PlainTextRenderer
{
    public function __construct(private readonly UrlSegmenter $urlSegmenter) {}

    /** @return list<array{type: 'text'|'link', text: string, url?: string}> */
    public function render(string $text): array
    {
        return $this->urlSegmenter->segment($text);
    }
}
