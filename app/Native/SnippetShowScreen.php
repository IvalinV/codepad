<?php

namespace App\Native;

use App\Enums\Language;
use App\Models\Snippet;
use App\Support\Highlighting\HighlightedCode;
use App\Support\Highlighting\SnippetRenderer;
use App\Support\Preferences;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Dialog;
use Native\Mobile\Facades\Share;
use NativePHP\Clipboard\Facades\Clipboard;

/**
 * Reading a snippet: highlighted code, and the four things you actually came
 * to do — copy it, share it, edit it, throw it away.
 *
 * Nothing on this screen re-highlights. Both themes were rendered at save
 * time, so opening a snippet is a read; the one exception is changing the
 * language, which is a deliberate re-derivation the user asked for.
 */
class SnippetShowScreen extends NativeComponent
{
    /**
     * Lines shown before the "show all" affordance. A read screen that
     * paints ten thousand nested text runs is a stutter the user pays for on
     * every open, and past a few hundred lines they are scrolling, not
     * reading.
     */
    private const LINE_CAP = 300;

    public bool $showAllLines = false;

    public bool $confirmingDelete = false;

    protected ?Snippet $snippet = null;

    public function mount(): void
    {
        $this->load();
    }

    /**
     * Returning from the edit screen. The body, title, language and renders
     * may all have changed underneath us, so re-read rather than trusting
     * the instance we pushed with.
     */
    public function onResume(): void
    {
        $this->showAllLines = false;
        $this->confirmingDelete = false;

        $this->load();
    }

    public function render(): View
    {
        if ($this->snippet === null) {
            return view('native.snippets.missing');
        }

        $highlighted = app(SnippetRenderer::class)
            ->renderFor($this->snippet, app(Preferences::class)->activeTheme());

        $totalLines = $highlighted?->lineCount() ?? $this->bodyLineCount();
        $truncated = ! $this->showAllLines && $totalLines > self::LINE_CAP;

        return view('native.snippets.show', [
            'snippet' => $this->snippet,
            'highlighted' => $this->visibleLines($highlighted, $truncated),
            'body' => $this->visibleBody($highlighted, $truncated),
            'totalLines' => $totalLines,
            'truncated' => $truncated,
            'languages' => array_map(fn (Language $case): string => $case->label(), Language::cases()),
        ]);
    }

    public function copy(): void
    {
        if ($this->snippet === null) {
            return;
        }

        Clipboard::writeText($this->snippet->body);

        Dialog::toast('Copied to clipboard', 'short');
    }

    /**
     * Share the body as a text file. The Share plugin's document types are
     * pdf and txt only, so the snippet goes out as `.txt` whatever language
     * it is written in.
     */
    public function share(): void
    {
        if ($this->snippet === null) {
            return;
        }

        $path = storage_path('app/'.$this->shareFilename());
        file_put_contents($path, $this->snippet->body);

        Share::file($this->snippet->displayTitle(), $this->snippet->body, $path);
    }

    public function showEverything(): void
    {
        $this->showAllLines = true;
    }

    public function edit(): void
    {
        if ($this->snippet !== null) {
            $this->navigate("/snippets/{$this->snippet->id}/edit");
        }
    }

    public function confirmDelete(): void
    {
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    /**
     * Renders go with the snippet: the migration's cascade delete owns that,
     * so there is nothing to clean up here.
     */
    public function delete(): void
    {
        $this->snippet?->delete();
        $this->snippet = null;

        $this->back();
    }

    /**
     * Re-language a snippet. The stored renders are derived from the
     * language, so they are wrong the moment it changes — refreshing here
     * rather than leaving them stale keeps the read path a pure lookup.
     */
    public function changeLanguage(string $label): void
    {
        $language = Language::tryFromLabel($label);

        if ($this->snippet === null || $language === null || $language === $this->snippet->language) {
            return;
        }

        $this->snippet->update(['language' => $language]);

        app(SnippetRenderer::class)->refresh($this->snippet);
        app(Preferences::class)->rememberLanguage($language);
    }

    private function load(): void
    {
        $this->snippet = Snippet::query()->find((int) $this->param('snippet'));
    }

    private function visibleLines(?HighlightedCode $highlighted, bool $truncated): ?HighlightedCode
    {
        if ($highlighted === null) {
            return null;
        }

        return $truncated ? $highlighted->truncate(self::LINE_CAP) : $highlighted;
    }

    /**
     * The plain-text fallback, cut to the same cap. Only reached when there
     * is no usable render — a snippet saved before its refresh landed, or
     * one whose body has moved on since. That is a normal state, not an
     * error, so it renders as ordinary unhighlighted code.
     */
    private function visibleBody(?HighlightedCode $highlighted, bool $truncated): ?string
    {
        if ($highlighted !== null) {
            return null;
        }

        $body = (string) $this->snippet?->body;

        if (! $truncated) {
            return $body;
        }

        return implode("\n", array_slice(preg_split('/\R/u', $body) ?: [], 0, self::LINE_CAP));
    }

    private function bodyLineCount(): int
    {
        return count(preg_split('/\R/u', (string) $this->snippet?->body) ?: ['']);
    }

    /**
     * A filesystem-safe name derived from the display title, so what lands
     * in the share sheet is recognisable rather than `snippet-14.txt`.
     */
    private function shareFilename(): string
    {
        $slug = str($this->snippet?->displayTitle() ?? '')->slug()->limit(40, '')->value();

        return ($slug === '' ? 'snippet' : $slug).'.txt';
    }
}
