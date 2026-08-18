# Codepad

An offline, on-device mobile app for capturing code snippets from the clipboard and retrieving them later with native syntax highlighting.

Codepad is a capture-and-retrieve tool, not an editor. Snippets live in on-device SQLite — there is no backend, no sync, and no accounts.

## Features

- **Clipboard capture** — one tap from the list screen opens a new snippet pre-filled from the clipboard
- **Native syntax highlighting** — code is highlighted at save time by [Phiki](https://github.com/phikiphp/phiki) and rendered as real native UI (nested `<native:text>` runs via NativePHP's SuperNative renderer), not a WebView
- **16 languages** — PHP, JavaScript, TypeScript, Python, Go, Rust, Java, C#, Ruby, SQL, Bash, JSON, YAML, HTML, CSS, plus plain text
- **Light and dark themes** — every snippet is rendered for both themes up front, so switching is instant
- **Search and language filter** — fast `LIKE`-based search over titles and bodies
- **Export / import** — the whole library round-trips as a JSON archive (shared out as `.txt`, imported by paste); import is additive and can never delete existing snippets
- **Sane limits** — 100 KB body cap per snippet, read view truncates at 300 lines with a "show all" affordance

## Tech stack

- **Laravel 13** · **PHP 8.4**
- **NativePHP Mobile ~4.0** (SuperNative) with the clipboard, share, and UI plugins
- **phiki/phiki 2.x** for TextMate-based highlighting
- **SQLite** (the only driver NativePHP supports)
- **Pest 5** for tests

## How it works

Highlighting happens at **save time**, not view time. Phiki tokenises the snippet into a normalised structure of coloured runs (`array<line, array<run{text, color}>>`), which is stored per theme in a derived `snippet_renders` cache table. Each render is guarded by a hash of `(body, language)` — if the snippet changes, the stale render is ignored and the read view falls back to plain text rather than showing wrong colours. The read view maps those runs to nested native text elements; editing is deliberately plain (native text inputs cannot render styled spans).

Two Phiki grammars (`csharp`, `ruby`) ship with patterns oniguruma rejects; patched copies live in `resources/grammars/` and are registered on the Phiki singleton in `AppServiceProvider`.

## Project structure

```
app/
├── Enums/                  # Language (the 16-language allowlist), ThemeVariant
├── Models/                 # Snippet, SnippetRender (the derived render cache)
├── Native/                 # SuperNative screens: list, show, edit, settings, tab layout
├── Support/
│   ├── Highlighting/       # Highlighter, PhikiTokenMapper, HighlightedCode, SnippetRenderer
│   ├── Preferences.php     # Small key/value store (e.g. last-used language)
│   └── SnippetArchive.php  # JSON export / additive import
resources/
├── grammars/               # Patched C# and Ruby grammars
└── views/native/           # Blade views for the native screens
```

## Development

```bash
composer setup          # install deps, generate key, migrate, build assets
php artisan native:jump # start the NativePHP dev server for on-device testing
```

Requires iOS 18.2+ / Android 26+ (floors imposed by the clipboard plugin).

```bash
php artisan test        # run the Pest suite
vendor/bin/pint --dirty # format changed files
```

## Documentation

- `PLAN.md` — the full v1 implementation plan, including the architecture decisions and the explicit out-of-scope list (tags, folders, sync, highlighting while editing, …)
- `CLAUDE.md` — guidelines for AI coding assistants working in this repo

## License

Codepad is open-sourced software licensed under the [MIT license](LICENSE.md).
