<?php

namespace App\Native;

use App\Enums\Language;
use App\Models\Snippet;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * The app's front door: every snippet, newest first, narrowed by a search
 * term and an optional language.
 *
 * Filtering is deliberately a plain database query rather than an in-memory
 * filter over a cached collection — `Snippet::search()` is indexed and the
 * library is the only thing on screen, so re-querying per keystroke is
 * cheaper than holding every body in memory.
 */
class SnippetListScreen extends NativeComponent
{
    /**
     * How many leading lines of the body each row previews. Two is enough
     * to tell two similar snippets apart without turning the list into a
     * wall of code.
     */
    private const PREVIEW_LINES = 2;

    public string $search = '';

    /**
     * The active language filter as its backing value rather than a
     * `Language` — a bound property has to survive the round trip through
     * the native bridge, which speaks JSON scalars.
     */
    public ?string $language = null;

    public function render(): View
    {
        $snippets = $this->snippets();

        return view('native.snippets.index', [
            'snippets' => $snippets,
            'languages' => Language::cases(),
            'activeLanguage' => $this->activeLanguage(),
            'hasFilters' => $this->hasFilters(),
            'libraryIsEmpty' => $snippets->isEmpty() && ! $this->hasFilters(),
        ]);
    }

    /**
     * Select a language, or clear the filter when the already-active chip
     * is tapped. The chip sends its new selected state as a second
     * argument; the flip covers both directions without reading it, so a
     * chip that reports state we did not expect cannot desynchronise.
     */
    public function toggleLanguage(string $language): void
    {
        $this->language = $this->language === $language ? null : $language;
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->language = null;
    }

    public function open(int $snippet): void
    {
        $this->navigate("/snippets/{$snippet}");
    }

    public function create(): void
    {
        $this->navigate('/snippets/new');
    }

    public function openSettings(): void
    {
        $this->navigate('/settings');
    }

    /** The first few non-empty lines of a body, for the row preview. */
    public function preview(Snippet $snippet): string
    {
        $lines = preg_split('/\R/u', (string) $snippet->body) ?: [];

        return implode("\n", array_slice(
            array_values(array_filter($lines, fn (string $line): bool => trim($line) !== '')),
            0,
            self::PREVIEW_LINES,
        ));
    }

    /** @return Collection<int, Snippet> */
    private function snippets(): Collection
    {
        return Snippet::query()
            ->search($this->search)
            ->forLanguage($this->activeLanguage())
            ->recent()
            ->get();
    }

    private function activeLanguage(): ?Language
    {
        return $this->language === null ? null : Language::tryFrom($this->language);
    }

    private function hasFilters(): bool
    {
        return trim($this->search) !== '' || $this->language !== null;
    }
}
