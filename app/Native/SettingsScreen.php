<?php

namespace App\Native;

use App\Enums\ThemeVariant;
use App\Models\Snippet;
use App\Support\Highlighting\SnippetRenderer;
use App\Support\Preferences;
use App\Support\SnippetArchive;
use Illuminate\View\View;
use InvalidArgumentException;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Share;
use NativePHP\Clipboard\Facades\Clipboard;

/**
 * Backup and appearance.
 *
 * Codepad has no accounts and no server, so export is not a convenience —
 * it is the only copy of the library that exists anywhere but this device.
 * The screen says so rather than filing it under a footnote.
 */
class SettingsScreen extends NativeComponent
{
    /**
     * `.txt` rather than `.json`: the Share plugin's supported document
     * types are pdf and txt only, and a backup the OS refuses to hand to
     * another app is not a backup.
     */
    private const BACKUP_FILENAME = 'codepad-backup.txt';

    /** 'system', or a `ThemeVariant` value. */
    public string $theme = 'system';

    public string $backup = '';

    public ?string $status = null;

    public ?string $problem = null;

    public function mount(): void
    {
        $this->theme = app(Preferences::class)->themePreference()?->value ?? 'system';
    }

    public function render(): View
    {
        return view('native.settings', [
            'snippetCount' => Snippet::query()->count(),
            'themes' => [
                'system' => 'Follow system',
                ThemeVariant::Light->value => 'Light',
                ThemeVariant::Dark->value => 'Dark',
            ],
        ]);
    }

    public function chooseTheme(string $theme): void
    {
        $variant = ThemeVariant::tryFrom($theme);

        if ($variant === null && $theme !== 'system') {
            return;
        }

        $this->theme = $variant?->value ?? 'system';

        app(Preferences::class)->rememberTheme($variant);
    }

    public function export(): void
    {
        $path = storage_path('app/'.self::BACKUP_FILENAME);
        $count = Snippet::query()->count();

        file_put_contents($path, app(SnippetArchive::class)->export());

        Share::file('Codepad backup', 'Codepad snippet backup', $path);

        $this->problem = null;
        $this->status = "Exported {$count} ".str('snippet')->plural($count).'.';
    }

    public function pasteBackup(): void
    {
        $this->backup = (string) Clipboard::readText();
        $this->status = null;
        $this->problem = null;
    }

    /**
     * Import, then render what arrived.
     *
     * A backup carries bodies but no renders — they are derived data and are
     * deliberately not exported — so imported snippets would otherwise open
     * as plain text until each was next edited. Only the new rows are
     * refreshed: everything already in the library still has renders that
     * match its own hash, and re-deriving them would cost a tokenise per
     * snippet for no change at all.
     */
    public function import(): void
    {
        $highWaterMark = (int) Snippet::query()->max('id');

        try {
            $imported = app(SnippetArchive::class)->import($this->backup);
        } catch (InvalidArgumentException $failure) {
            $this->status = null;
            $this->problem = $failure->getMessage();

            return;
        }

        Snippet::query()
            ->where('id', '>', $highWaterMark)
            ->each(fn (Snippet $snippet) => app(SnippetRenderer::class)->refresh($snippet));

        $this->backup = '';
        $this->problem = null;
        $this->status = "Imported {$imported} ".str('snippet')->plural($imported).'.';
    }
}
