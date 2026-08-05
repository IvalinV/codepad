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
            'languages' => $this->languageFilters(),
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

    /**
     * The languages worth offering as filters.
     *
     * Only those the library actually holds: the full enum offers sixteen
     * chips to a user who owns three, and the thirteen extras are filters
     * that can only ever return nothing. Derived from the whole library
     * rather than from the current query, so the row does not reflow on
     * every keystroke while the user is searching.
     *
     * Ordered by filtering `Language::cases()` rather than by the database,
     * which keeps chip positions fixed — ordering by insertion or by
     * frequency would move a chip out from under the thumb reaching for it.
     *
     * Empty below two languages: narrowing an all-PHP library to PHP changes
     * nothing, so a lone chip is chrome rather than a control.
     *
     * @return array<int, Language>
     */
    private function languageFilters(): array
    {
        $present = Snippet::query()->distinct()->pluck('language')->all();

        if (count($present) < 2) {
            return [];
        }

        return array_values(array_filter(
            Language::cases(),
            fn (Language $language): bool => in_array($language, $present, true),
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
