# Codepad v1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An offline, on-device mobile app for capturing code snippets from the clipboard and retrieving them later with native syntax highlighting.

**Architecture:** Codepad is a capture-and-retrieve tool, not an editor. Snippets live in on-device SQLite with no backend and no accounts. Code is highlighted at **save time** by Phiki, parsed into a normalised token structure, and stored per theme in a derived `snippet_renders` cache table guarded by a content hash. The read view renders those tokens as nested `<native:text>` runs via NativePHP v4's SuperNative renderer — real native UI, no WebView.

**Tech Stack:** Laravel 12, PHP 8.4, NativePHP Mobile `~4.0.0` (SuperNative), `nativephp/mobile-clipboard`, `phiki/phiki`, SQLite, Pest 5.

## Global Constraints

- **Platform floors:** iOS 18.2 minimum, Android 26 minimum (imposed by `nativephp/mobile-clipboard`).
- **NativePHP:** `"nativephp/mobile": "~4.0.0"` — tilde with full minimum patch, per the framework's versioning guidance.
- **No new base directories.** `CLAUDE.md` forbids creating new top-level folders without approval. New code goes under existing `app/`, `resources/`, `database/`, `tests/`.
- **No `DB::`.** Use `Model::query()` and Eloquent relationships with return type hints.
- **No `env()` outside `config/`.** Use `config()`.
- **Explicit return types** on every method and function. PHP 8 constructor property promotion. Curly braces on all control structures.
- **Enum cases are TitleCase.**
- **Tests are Pest 5** (`pestphp/pest: ^5.0` — note `CLAUDE.md` says v4; the lockfile is authoritative). Feature tests in `tests/Feature`, unit tests in `tests/Unit`.
- **Run `vendor/bin/pint --dirty`** before every commit.
- **Body size cap:** 102400 bytes (100 KB) per snippet.
- **Read view line cap:** 300 lines before a "show all" affordance. Provisional — Task 0.1 may remove it.
- **Languages:** exactly the 16 cases in `App\Enums\Language` (15 languages plus `PlainText`). Every other grammar is pruned from the bundle.
- **Themes:** exactly two, light and dark.

## Empirical unknowns this plan is built around

Three assumptions are unverified and each can invalidate schema or scope. **Phase 0 resolves them before Phase 1 begins.** Do not skip them; each has an explicit decision rule that feeds later tasks.

## File Structure

**Created:**

| Path | Responsibility |
|---|---|
| `app/Enums/Language.php` | The 15-language allowlist; maps to a Phiki `Grammar` case |
| `app/Enums/ThemeVariant.php` | Light/dark; maps to a Phiki `Theme` case |
| `app/Models/Snippet.php` | Snippet record; title fallback; search scopes |
| `app/Models/SnippetRender.php` | One cached render per (snippet, theme) |
| `app/Support/Highlighting/HighlightedCode.php` | Value object: lines of coloured token runs |
| `app/Support/Highlighting/PhikiHtmlParser.php` | Phiki HTML → `HighlightedCode` |
| `app/Support/Highlighting/Highlighter.php` | Phiki invocation + parsing |
| `app/Support/Highlighting/SnippetRenderer.php` | Hash guard, refresh, read-through |
| `app/Support/SnippetArchive.php` | JSON export / import |
| `app/Http/Requests/StoreSnippetRequest.php` | Validation incl. the 100 KB cap |
| `app/Http/Requests/UpdateSnippetRequest.php` | Same rules for updates |
| `database/migrations/*_create_snippets_table.php` | |
| `database/migrations/*_create_snippet_renders_table.php` | |
| `database/factories/SnippetFactory.php` | |
| `resources/views/native/*.blade.php` | Screens |
| `resources/views/native/partials/highlighted-code.blade.php` | Token runs → nested `<native:text>` |

**Modified:** `composer.json`, `config/nativephp.php`, `.env` / `.env.example`, `routes/web.php`, `tests/Pest.php`, `app/Providers/AppServiceProvider.php`.

**A note on the screen tasks (14–18).** Tasks 1–13 are pure PHP and fully specified — exact code, exact signatures. Tasks 14–18 touch SuperNative's component base class, routing and EDGE element namespaces, which this plan has **not** verified against an installed copy (the docs show both `Native\Mobile\Edge\Elements\*` and `Native\Mobile\UI\Elements\*`). Before starting Task 14, read `vendor/nativephp/mobile/src/` and the v4 docs, and correct the imports in those tasks. Do not trust the import lines below over the installed package.

---

## Phase 0 — Spikes

These produce measurements and decisions, not shipped code. Work in `/tmp` or a scratch branch; nothing here gets committed to `app/`.

### Task 0.1: Highlight + render a large file on a real device

**Why:** Decides whether highlight-on-save is viable and whether the 300-line read cap is needed. Phiki is ~13.2 MB and TextMate tokenising is regex-heavy; the SuperNative renderer publishes no documented node limit, and one snippet is a single `<native:text>` containing thousands of nested runs.

- [ ] **Step 1: Install Phiki in a scratch app and pick a fixture**

Use a real 1,000-line source file (e.g. `vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php`).

- [ ] **Step 2: Measure highlighting time on-device, not on your laptop**

```php
$code = file_get_contents($path);
$start = hrtime(true);
$html = (new Phiki\Phiki)->codeToHtml($code, Phiki\Grammar\Grammar::Php, Phiki\Theme\Theme::GithubLight)->toString();
$ms = (hrtime(true) - $start) / 1_000_000;
Log::info("phiki: {$ms}ms, ".strlen($html).' bytes');
```

Read it back with `php artisan native:tail`.

- [ ] **Step 3: Render the parsed runs in a screen and time first paint**

- [ ] **Step 4: Record the decision**

| Result | Action |
|---|---|
| < 300 ms | Highlight synchronously on save. Consider dropping the 300-line cap. |
| 300 ms – 2 s | Keep highlight-on-save but persist the body first and render asynchronously. Keep the cap. |
| > 2 s, or renderer stutters | Abandon Phiki. Fall back to a hand-rolled regex highlighter over the same `HighlightedCode` interface — Tasks 5–7 are written so only `Highlighter` changes. |

### Task 0.2: iOS paste-consent behaviour

**Why:** The clipboard plugin claims *"no permissions"*. iOS shows a system paste-consent prompt for programmatic pasteboard reads under some conditions. If it fires on every tap, the Task 17 capture flow needs redesigning.

- [ ] **Step 1: Build a screen with one button calling `Clipboard::readText()`**
- [ ] **Step 2: On a physical iPhone, copy text in Safari, switch to the app, tap it. Repeat five times.**
- [ ] **Step 3: Record whether a consent prompt appears, and whether it recurs**

If it prompts every time: in Task 17, do not auto-fill. Show an explicit "Paste from clipboard" button so the prompt is a consequence of a deliberate user action rather than a surprise on screen open.

### Task 0.3: Confirm the SQLite file is in the OS backup set

**Why:** Q8 relies on OS backup as the new-phone story. If the database sits in an excluded directory you will believe you have backups and have none. The docs confirm *"If a user deletes your application from their device, any databases are also deleted."*

- [ ] **Step 1: Log the resolved database path on device**

```php
Log::info('db: '.config('database.connections.sqlite.database'));
```

- [ ] **Step 2: Determine whether that path is backed up**

iOS: it must not be under `Library/Caches` or `tmp`, and must not carry the `isExcludedFromBackup` flag. Android: check `android:allowBackup` and any `data_extraction_rules` / `full_backup_content` in the generated manifest.

- [ ] **Step 3: Verify end-to-end** — back up the device, uninstall, restore, confirm snippets return.
- [ ] **Step 4: If excluded**, treat manual export (Task 11) as the *only* backup story and make it prominent in the settings screen rather than a footnote.

---

## Phase 1 — Foundation

### Task 1: Upgrade to NativePHP v4 and establish app identity

**Files:**
- Modify: `composer.json`, `.env`, `.env.example`, `tests/Pest.php`, `app/Providers/AppServiceProvider.php`

**Interfaces:**
- Produces: `Clipboard` facade available app-wide; `RefreshDatabase` active for feature tests.

- [ ] **Step 1: Move to v4 and install the clipboard plugin**

```bash
composer require "nativephp/mobile:~4.0.0"
php artisan native:plugin:uninstall --core-v4 --force
composer require nativephp/mobile-clipboard
php artisan native:plugin:register nativephp/mobile-clipboard
composer require phiki/phiki
```

The `plugins.nativephp.com` repository is already declared in `composer.json`. The v4 upgrade's only breaking change is that `Device`, `Dialog`, `File` and `System` moved into core; you have no application code using them.

- [ ] **Step 2: Set real app identity**

In `.env` and `.env.example`:

```
APP_NAME=Codepad
NATIVEPHP_APP_ID=com.ivalinvenkov.codepad
NATIVEPHP_APP_VERSION="0.1.0"
NATIVEPHP_APP_VERSION_CODE="1"
```

The starter ships `APP_NAME=Laravel` and a placeholder ID (`com.ivalinvenkov.crystalambersilver`). The app ID is **permanent once published to either store** — change it now.

- [ ] **Step 3: Enable `RefreshDatabase` for feature tests**

In `tests/Pest.php`, uncomment the trait:

```php
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
```

- [ ] **Step 4: Remove `URL::forceHttps()`**

In `app/Providers/AppServiceProvider.php`, delete the `URL::forceHttps()` call and the now-unused `use Illuminate\Support\Facades\URL;`. On device the app is not served over HTTPS — iOS uses a `php://` scheme — and forcing HTTPS on generated URLs is wrong there.

- [ ] **Step 5: Verify the suite still runs**

Run: `php artisan test`
Expected: PASS (the two example tests).

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "chore: upgrade to NativePHP Mobile v4, add clipboard and phiki, set app identity"
```

---

### Task 2: The `Language` enum

**Files:**
- Create: `app/Enums/Language.php`
- Test: `tests/Unit/Enums/LanguageTest.php`

**Interfaces:**
- Produces: `Language::tryFrom(string): ?Language`, `Language::grammar(): Grammar`, `Language::label(): string`, `Language::PlainText`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\Language;
use Phiki\Grammar\Grammar;

it('maps every case to a phiki grammar', function (Language $language) {
    expect($language->grammar())->toBeInstanceOf(Grammar::class);
})->with(Language::cases());

it('gives every case a human label', function (Language $language) {
    expect($language->label())->not->toBeEmpty();
})->with(Language::cases());

it('resolves from a stored string value', function () {
    expect(Language::tryFrom('php'))->toBe(Language::Php)
        ->and(Language::tryFrom('nonsense'))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Enums/LanguageTest.php`
Expected: FAIL — `Class "App\Enums\Language" not found`.

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

use Phiki\Grammar\Grammar;

enum Language: string
{
    case PlainText = 'plaintext';
    case Php = 'php';
    case JavaScript = 'javascript';
    case TypeScript = 'typescript';
    case Python = 'python';
    case Go = 'go';
    case Rust = 'rust';
    case Java = 'java';
    case CSharp = 'csharp';
    case Ruby = 'ruby';
    case Sql = 'sql';
    case Bash = 'bash';
    case Json = 'json';
    case Yaml = 'yaml';
    case Html = 'html';
    case Css = 'css';

    public function grammar(): Grammar
    {
        return match ($this) {
            self::PlainText => Grammar::Txt,
            self::Php => Grammar::Php,
            self::JavaScript => Grammar::Javascript,
            self::TypeScript => Grammar::Typescript,
            self::Python => Grammar::Python,
            self::Go => Grammar::Go,
            self::Rust => Grammar::Rust,
            self::Java => Grammar::Java,
            self::CSharp => Grammar::Csharp,
            self::Ruby => Grammar::Ruby,
            self::Sql => Grammar::Sql,
            self::Bash => Grammar::Bash,
            self::Json => Grammar::Json,
            self::Yaml => Grammar::Yaml,
            self::Html => Grammar::Html,
            self::Css => Grammar::Css,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PlainText => 'Plain text',
            self::Php => 'PHP',
            self::JavaScript => 'JavaScript',
            self::TypeScript => 'TypeScript',
            self::Python => 'Python',
            self::Go => 'Go',
            self::Rust => 'Rust',
            self::Java => 'Java',
            self::CSharp => 'C#',
            self::Ruby => 'Ruby',
            self::Sql => 'SQL',
            self::Bash => 'Bash',
            self::Json => 'JSON',
            self::Yaml => 'YAML',
            self::Html => 'HTML',
            self::Css => 'CSS',
        };
    }
}
```

**The `Grammar::` case names above are unverified.** Open `vendor/phiki/phiki/src/Grammar/Grammar.php` and correct any that don't exist. The test in Step 1 catches the mismatch only if the enum compiles — a missing case is a fatal error, which is the loud failure you want.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Enums/LanguageTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Enums/Language.php tests/Unit/Enums/LanguageTest.php
git commit -m "feat: add Language enum mapping the allowlist to phiki grammars"
```

---

### Task 3: The `ThemeVariant` enum

**Files:**
- Create: `app/Enums/ThemeVariant.php`
- Test: `tests/Unit/Enums/ThemeVariantTest.php`

**Interfaces:**
- Produces: `ThemeVariant::Light`, `ThemeVariant::Dark`, `ThemeVariant::phikiTheme(): Theme`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\ThemeVariant;
use Phiki\Theme\Theme;

it('has exactly two variants', function () {
    expect(ThemeVariant::cases())->toHaveCount(2);
});

it('maps each variant to a phiki theme', function (ThemeVariant $variant) {
    expect($variant->phikiTheme())->toBeInstanceOf(Theme::class);
})->with(ThemeVariant::cases());
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Enums/ThemeVariantTest.php`
Expected: FAIL — `Class "App\Enums\ThemeVariant" not found`.

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

use Phiki\Theme\Theme;

enum ThemeVariant: string
{
    case Light = 'light';
    case Dark = 'dark';

    public function phikiTheme(): Theme
    {
        return match ($this) {
            self::Light => Theme::GithubLight,
            self::Dark => Theme::GithubDark,
        };
    }
}
```

Verify both `Theme::` cases exist in `vendor/phiki/phiki/src/Theme/Theme.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Enums/ThemeVariantTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Enums/ThemeVariant.php tests/Unit/Enums/ThemeVariantTest.php
git commit -m "feat: add ThemeVariant enum"
```

---

### Task 4: Schema — `snippets` and `snippet_renders`

**Files:**
- Create: `database/migrations/<ts>_create_snippets_table.php`, `database/migrations/<ts>_create_snippet_renders_table.php`, `app/Models/Snippet.php`, `app/Models/SnippetRender.php`, `database/factories/SnippetFactory.php`
- Test: `tests/Feature/Models/SnippetTest.php`

**Interfaces:**
- Produces: `Snippet` with `title` (nullable), `body`, `language` (cast to `Language`); `Snippet::renders(): HasMany`; `SnippetRender` with `theme` (cast to `ThemeVariant`), `content` (cast to `array`), `hash`; `Snippet::factory()`.

**Design note:** renders live in their own table deliberately. The list screen never needs them, so they stay out of `select *` on the hot query; "rebuild every render" becomes a truncate rather than a migration plus backfill; and a third theme later is a row, not a schema change. Absence of a row is a meaningful state — it means "not highlighted yet", which is what lets the save path persist the body immediately without null-column ambiguity.

- [ ] **Step 1: Generate the scaffolding**

```bash
php artisan make:model Snippet --migration --factory --no-interaction
php artisan make:model SnippetRender --migration --no-interaction
```

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Models\Snippet;
use App\Models\SnippetRender;

it('stores a snippet with a nullable title', function () {
    $snippet = Snippet::factory()->create(['title' => null, 'body' => '<?php echo 1;']);

    expect($snippet->fresh()->title)->toBeNull()
        ->and($snippet->fresh()->body)->toBe('<?php echo 1;');
});

it('casts language to the enum', function () {
    $snippet = Snippet::factory()->create(['language' => Language::Php]);

    expect($snippet->fresh()->language)->toBe(Language::Php);
});

it('has many renders', function () {
    $snippet = Snippet::factory()->create();

    $snippet->renders()->create([
        'theme' => ThemeVariant::Light,
        'content' => [[['text' => 'echo', 'color' => '#d73a49']]],
        'hash' => 'abc123',
    ]);

    expect($snippet->renders)->toHaveCount(1)
        ->and($snippet->renders->first()->theme)->toBe(ThemeVariant::Light)
        ->and($snippet->renders->first()->content)->toBeArray();
});

it('deletes renders when the snippet is deleted', function () {
    $snippet = Snippet::factory()->create();
    $snippet->renders()->create([
        'theme' => ThemeVariant::Dark,
        'content' => [],
        'hash' => 'abc123',
    ]);

    $snippet->delete();

    expect(SnippetRender::query()->count())->toBe(0);
});

it('permits only one render per snippet and theme', function () {
    $snippet = Snippet::factory()->create();
    $attributes = ['theme' => ThemeVariant::Light, 'content' => [], 'hash' => 'x'];

    $snippet->renders()->create($attributes);

    expect(fn () => $snippet->renders()->create($attributes))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/Models/SnippetTest.php`
Expected: FAIL — no such table / undefined relationship.

- [ ] **Step 4: Write the migrations**

`create_snippets_table`:

```php
Schema::create('snippets', function (Blueprint $table): void {
    $table->id();
    $table->string('title')->nullable();
    $table->text('body');
    $table->string('language')->default('plaintext');
    $table->timestamps();

    $table->index('updated_at');
});
```

`create_snippet_renders_table`:

```php
Schema::create('snippet_renders', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('snippet_id')->constrained()->cascadeOnDelete();
    $table->string('theme');
    $table->json('content');
    $table->string('hash');
    $table->timestamps();

    $table->unique(['snippet_id', 'theme']);
});
```

- [ ] **Step 5: Write the models**

`app/Models/Snippet.php`:

```php
<?php

namespace App\Models;

use App\Enums\Language;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Snippet extends Model
{
    /** @use HasFactory<\Database\Factories\SnippetFactory> */
    use HasFactory;

    protected $fillable = ['title', 'body', 'language'];

    /** @return HasMany<SnippetRender, $this> */
    public function renders(): HasMany
    {
        return $this->hasMany(SnippetRender::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['language' => Language::class];
    }
}
```

`app/Models/SnippetRender.php`:

```php
<?php

namespace App\Models;

use App\Enums\ThemeVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnippetRender extends Model
{
    protected $fillable = ['theme', 'content', 'hash'];

    /** @return BelongsTo<Snippet, $this> */
    public function snippet(): BelongsTo
    {
        return $this->belongsTo(Snippet::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'theme' => ThemeVariant::class,
            'content' => 'array',
        ];
    }
}
```

- [ ] **Step 6: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Enums\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Snippet> */
class SnippetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'body' => "<?php\n\nfunction handle(): void\n{\n    echo 'hi';\n}",
            'language' => Language::Php,
        ];
    }

    public function untitled(): static
    {
        return $this->state(fn (array $attributes): array => ['title' => null]);
    }
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test tests/Feature/Models/SnippetTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty
git add database/ app/Models/
git commit -m "feat: add snippets and snippet_renders schema"
```

---

### Task 5: `HighlightedCode` and `PhikiHtmlParser`

**Files:**
- Create: `app/Support/Highlighting/HighlightedCode.php`, `app/Support/Highlighting/PhikiHtmlParser.php`, `tests/Fixtures/phiki-php-github-light.html`
- Test: `tests/Unit/Highlighting/PhikiHtmlParserTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `HighlightedCode::__construct(array $lines)`, `HighlightedCode::toArray(): array`, `HighlightedCode::fromArray(array $lines): self`, `HighlightedCode::lineCount(): int`, `PhikiHtmlParser::parse(string $html): HighlightedCode`.

The stored shape is `array<int, array<int, array{text: string, color: string}>>` — a list of lines, each a list of coloured runs. Storing normalised tokens rather than HTML means the Blade view is a trivial loop and every part of the pipeline is unit-testable without a device.

- [ ] **Step 1: Capture a real fixture — do not hand-write it**

```bash
php artisan tinker --execute="file_put_contents('tests/Fixtures/phiki-php-github-light.html', (new Phiki\Phiki)->codeToHtml(\"<?php\n\nfunction handle(): void\n{\n    echo 'hi';\n}\n\", Phiki\Grammar\Grammar::Php, Phiki\Theme\Theme::GithubLight)->toString());"
```

Read the file. The parser below walks the DOM generically rather than assuming class names, but you need to see the real markup to know whether lines are wrapped in elements or separated by newline text nodes.

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Support\Highlighting\HighlightedCode;
use App\Support\Highlighting\PhikiHtmlParser;

beforeEach(function () {
    $this->parser = new PhikiHtmlParser;
    $this->html = file_get_contents(base_path('tests/Fixtures/phiki-php-github-light.html'));
});

it('returns a HighlightedCode value object', function () {
    expect($this->parser->parse($this->html))->toBeInstanceOf(HighlightedCode::class);
});

it('preserves the source line count', function () {
    expect($this->parser->parse($this->html)->lineCount())->toBe(7);
});

it('assigns a hex colour to every run', function () {
    foreach ($this->parser->parse($this->html)->toArray() as $line) {
        foreach ($line as $run) {
            expect($run['color'])->toMatch('/^#[0-9a-f]{3,8}$/i');
        }
    }
});

it('reconstructs the original source when runs are concatenated', function () {
    $text = collect($this->parser->parse($this->html)->toArray())
        ->map(fn (array $line): string => collect($line)->pluck('text')->implode(''))
        ->implode("\n");

    expect(trim($text))->toContain('function handle(): void')
        ->and(trim($text))->toContain("echo 'hi';");
});

it('round-trips through toArray and fromArray', function () {
    $parsed = $this->parser->parse($this->html);

    expect(HighlightedCode::fromArray($parsed->toArray())->toArray())->toBe($parsed->toArray());
});
```

The reconstruction test is the important one: it proves no source text is silently dropped, which is the failure mode that would otherwise ship a snippet missing characters.

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test tests/Unit/Highlighting/PhikiHtmlParserTest.php`
Expected: FAIL — `Class "App\Support\Highlighting\PhikiHtmlParser" not found`.

- [ ] **Step 4: Write `HighlightedCode`**

```php
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
```

- [ ] **Step 5: Write `PhikiHtmlParser`**

```php
<?php

namespace App\Support\Highlighting;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

final class PhikiHtmlParser
{
    private const DEFAULT_COLOR = '#24292e';

    public function parse(string $html): HighlightedCode
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $lineNodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' line ')]");

        if ($lineNodes !== false && $lineNodes->length > 0) {
            $lines = [];

            foreach ($lineNodes as $lineNode) {
                $lines[] = $this->runsIn($lineNode, self::DEFAULT_COLOR);
            }

            return new HighlightedCode($lines);
        }

        return $this->parseByNewline($xpath);
    }

    /** @return array<int, array{text: string, color: string}> */
    private function runsIn(DOMNode $node, string $inheritedColor): array
    {
        $runs = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                if ($child->textContent !== '') {
                    $runs[] = ['text' => $child->textContent, 'color' => $inheritedColor];
                }

                continue;
            }

            if ($child instanceof DOMElement) {
                $runs = array_merge($runs, $this->runsIn($child, $this->colorOf($child, $inheritedColor)));
            }
        }

        return $runs;
    }

    private function colorOf(DOMElement $element, string $fallback): string
    {
        if (preg_match('/color:\s*([^;"]+)/i', $element->getAttribute('style'), $matches) === 1) {
            return trim($matches[1]);
        }

        return $fallback;
    }

    private function parseByNewline(DOMXPath $xpath): HighlightedCode
    {
        $code = $xpath->query('//code')?->item(0);

        if (! $code instanceof DOMElement) {
            return new HighlightedCode([]);
        }

        $lines = [[]];

        foreach ($this->runsIn($code, self::DEFAULT_COLOR) as $run) {
            $segments = explode("\n", $run['text']);

            foreach ($segments as $index => $segment) {
                if ($index > 0) {
                    $lines[] = [];
                }

                if ($segment !== '') {
                    $lines[count($lines) - 1][] = ['text' => $segment, 'color' => $run['color']];
                }
            }
        }

        return new HighlightedCode($lines);
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Unit/Highlighting/PhikiHtmlParserTest.php`
Expected: PASS. If the line count assertion fails, adjust the expected count to match the fixture you actually captured — but do **not** relax the reconstruction test.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add app/Support/Highlighting/ tests/
git commit -m "feat: parse phiki html into normalised coloured token runs"
```

---

### Task 6: The `Highlighter` service

**Files:**
- Create: `app/Support/Highlighting/Highlighter.php`
- Test: `tests/Unit/Highlighting/HighlighterTest.php`

**Interfaces:**
- Consumes: `Language`, `ThemeVariant`, `PhikiHtmlParser`, `HighlightedCode`.
- Produces: `Highlighter::highlight(string $code, Language $language, ThemeVariant $theme): HighlightedCode`.

This is the **only** class that knows Phiki exists. If Task 0.1 rules Phiki out, this is the single file that gets replaced.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Support\Highlighting\HighlightedCode;
use App\Support\Highlighting\Highlighter;

it('highlights php into coloured runs', function () {
    $result = app(Highlighter::class)->highlight("<?php\necho 'hi';", Language::Php, ThemeVariant::Light);

    expect($result)->toBeInstanceOf(HighlightedCode::class)
        ->and($result->lineCount())->toBe(2);
});

it('produces different colours for the two themes', function () {
    $highlighter = app(Highlighter::class);
    $code = "<?php\necho 'hi';";

    $light = $highlighter->highlight($code, Language::Php, ThemeVariant::Light)->toArray();
    $dark = $highlighter->highlight($code, Language::Php, ThemeVariant::Dark)->toArray();

    expect($light)->not->toBe($dark);
});

it('handles an empty body without erroring', function () {
    expect(app(Highlighter::class)->highlight('', Language::PlainText, ThemeVariant::Light)->lineCount())
        ->toBeLessThanOrEqual(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Highlighting/HighlighterTest.php`
Expected: FAIL — `Class "App\Support\Highlighting\Highlighter" not found`.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Support\Highlighting;

use App\Enums\Language;
use App\Enums\ThemeVariant;
use Phiki\Phiki;

final class Highlighter
{
    public function __construct(
        private readonly Phiki $phiki,
        private readonly PhikiHtmlParser $parser,
    ) {}

    public function highlight(string $code, Language $language, ThemeVariant $theme): HighlightedCode
    {
        $html = $this->phiki
            ->codeToHtml($code, $language->grammar(), $theme->phikiTheme())
            ->toString();

        return $this->parser->parse($html);
    }
}
```

- [ ] **Step 4: Register `Phiki` in the container**

In `app/Providers/AppServiceProvider::register()`:

```php
$this->app->singleton(Phiki::class, fn (): Phiki => new Phiki);
```

Add `use Phiki\Phiki;` at the top.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Unit/Highlighting/HighlighterTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add app/
git commit -m "feat: add Highlighter wrapping phiki behind a stable interface"
```

---

### Task 7: `SnippetRenderer` — the derived cache

**Files:**
- Create: `app/Support/Highlighting/SnippetRenderer.php`
- Test: `tests/Feature/Highlighting/SnippetRendererTest.php`

**Interfaces:**
- Consumes: `Highlighter`, `Snippet`, `SnippetRender`, `ThemeVariant`, `HighlightedCode`.
- Produces: `SnippetRenderer::hashFor(Snippet $snippet): string`, `SnippetRenderer::refresh(Snippet $snippet): void`, `SnippetRenderer::renderFor(Snippet $snippet, ThemeVariant $theme): ?HighlightedCode`.

**The invariant this class exists to protect:** renders are derived from `(body, language, theme)`. If the body or language changes and the renders don't, the app displays PHP colouring over Python. `hashFor()` is the guard; `renderFor()` returns `null` on mismatch so the view falls back to plain text rather than lying.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\Language;
use App\Enums\ThemeVariant;
use App\Models\Snippet;
use App\Support\Highlighting\SnippetRenderer;

beforeEach(function () {
    $this->renderer = app(SnippetRenderer::class);
});

it('creates one render per theme', function () {
    $snippet = Snippet::factory()->create();

    $this->renderer->refresh($snippet);

    expect($snippet->renders()->count())->toBe(2);
});

it('returns a render for a fresh snippet', function () {
    $snippet = Snippet::factory()->create();
    $this->renderer->refresh($snippet);

    expect($this->renderer->renderFor($snippet->fresh(), ThemeVariant::Light))->not->toBeNull();
});

it('returns null when no render exists yet', function () {
    expect($this->renderer->renderFor(Snippet::factory()->create(), ThemeVariant::Light))->toBeNull();
});

it('returns null once the body has changed', function () {
    $snippet = Snippet::factory()->create();
    $this->renderer->refresh($snippet);

    $snippet->update(['body' => '<?php echo "different";']);

    expect($this->renderer->renderFor($snippet->fresh(), ThemeVariant::Light))->toBeNull();
});

it('returns null once the language has changed', function () {
    $snippet = Snippet::factory()->create(['language' => Language::Php]);
    $this->renderer->refresh($snippet);

    $snippet->update(['language' => Language::Python]);

    expect($this->renderer->renderFor($snippet->fresh(), ThemeVariant::Light))->toBeNull();
});

it('replaces rather than duplicates renders on repeated refresh', function () {
    $snippet = Snippet::factory()->create();

    $this->renderer->refresh($snippet);
    $snippet->update(['body' => '<?php echo "changed";']);
    $this->renderer->refresh($snippet->fresh());

    expect($snippet->renders()->count())->toBe(2)
        ->and($this->renderer->renderFor($snippet->fresh(), ThemeVariant::Dark))->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Highlighting/SnippetRendererTest.php`
Expected: FAIL — `Class "App\Support\Highlighting\SnippetRenderer" not found`.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Support\Highlighting;

use App\Enums\ThemeVariant;
use App\Models\Snippet;

final class SnippetRenderer
{
    public function __construct(private readonly Highlighter $highlighter) {}

    public function hashFor(Snippet $snippet): string
    {
        return hash('xxh128', $snippet->body.'|'.$snippet->language->value);
    }

    public function refresh(Snippet $snippet): void
    {
        $hash = $this->hashFor($snippet);

        foreach (ThemeVariant::cases() as $theme) {
            $highlighted = $this->highlighter->highlight($snippet->body, $snippet->language, $theme);

            $snippet->renders()->updateOrCreate(
                ['theme' => $theme],
                ['content' => $highlighted->toArray(), 'hash' => $hash],
            );
        }
    }

    public function renderFor(Snippet $snippet, ThemeVariant $theme): ?HighlightedCode
    {
        $render = $snippet->renders()->firstWhere('theme', $theme);

        if ($render === null || $render->hash !== $this->hashFor($snippet)) {
            return null;
        }

        return HighlightedCode::fromArray($render->content);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Highlighting/SnippetRendererTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Support/Highlighting/SnippetRenderer.php tests/
git commit -m "feat: add SnippetRenderer with hash-guarded derived render cache"
```

---

### Task 8: Title fallback

**Files:**
- Modify: `app/Models/Snippet.php`
- Test: `tests/Unit/Models/SnippetTitleTest.php`

**Interfaces:**
- Produces: `Snippet::displayTitle(): string`.

**Rule:** never write the derived label into the `title` column. Keeping user intent and our guess in separate places is what lets you tell them apart later.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Snippet;

it('prefers the user title', function () {
    $snippet = new Snippet(['title' => 'Retry helper', 'body' => "function x() {}"]);

    expect($snippet->displayTitle())->toBe('Retry helper');
});

it('falls back to the first non-blank line', function () {
    $snippet = new Snippet(['title' => null, 'body' => "\n\n   function handleRetry(): void\n{\n}"]);

    expect($snippet->displayTitle())->toBe('function handleRetry(): void');
});

it('truncates a long fallback', function () {
    $snippet = new Snippet(['title' => null, 'body' => str_repeat('a', 200)]);

    expect(mb_strlen($snippet->displayTitle()))->toBeLessThanOrEqual(60);
});

it('handles an entirely blank body', function () {
    expect((new Snippet(['title' => null, 'body' => "  \n\n  "]))->displayTitle())->toBe('Untitled snippet');
});

it('treats an empty-string title as absent', function () {
    $snippet = new Snippet(['title' => '', 'body' => 'const x = 1;']);

    expect($snippet->displayTitle())->toBe('const x = 1;');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/SnippetTitleTest.php`
Expected: FAIL — `Call to undefined method App\Models\Snippet::displayTitle()`.

- [ ] **Step 3: Add the method to `Snippet`**

```php
public function displayTitle(): string
{
    if (filled($this->title)) {
        return $this->title;
    }

    foreach (preg_split('/\R/', (string) $this->body) ?: [] as $line) {
        $trimmed = trim($line);

        if ($trimmed !== '') {
            return Str::limit($trimmed, 60);
        }
    }

    return 'Untitled snippet';
}
```

Add `use Illuminate\Support\Str;`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/SnippetTitleTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/Snippet.php tests/
git commit -m "feat: derive a display title from the first non-blank line"
```

---

### Task 9: Search and language filter scopes

**Files:**
- Modify: `app/Models/Snippet.php`
- Test: `tests/Feature/Models/SnippetSearchTest.php`

**Interfaces:**
- Produces: `Snippet::scopeSearch(Builder $query, ?string $term): void`, `Snippet::scopeForLanguage(Builder $query, ?Language $language): void`, `Snippet::scopeRecent(Builder $query): void`.

**`LIKE`, not FTS5.** FTS5's availability in the embedded runtime is undocumented, and `LIKE` over a personal library is instant. This is also the framework's own documented search pattern. Revisit only if the library reaches thousands of snippets.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\Language;
use App\Models\Snippet;

it('matches on the body', function () {
    Snippet::factory()->create(['title' => 'A', 'body' => 'array_map($fn, $items)']);
    Snippet::factory()->create(['title' => 'B', 'body' => 'echo "nope";']);

    expect(Snippet::query()->search('array_map')->pluck('title')->all())->toBe(['A']);
});

it('matches on the title', function () {
    Snippet::factory()->create(['title' => 'Retry helper', 'body' => 'x']);
    Snippet::factory()->create(['title' => 'Other', 'body' => 'y']);

    expect(Snippet::query()->search('retry')->pluck('title')->all())->toBe(['Retry helper']);
});

it('is case insensitive', function () {
    Snippet::factory()->create(['title' => 'Retry helper', 'body' => 'x']);

    expect(Snippet::query()->search('RETRY')->count())->toBe(1);
});

it('escapes wildcards so they match literally', function () {
    Snippet::factory()->create(['title' => 'literal', 'body' => 'SELECT 100%']);
    Snippet::factory()->create(['title' => 'other', 'body' => 'SELECT 1']);

    expect(Snippet::query()->search('100%')->pluck('title')->all())->toBe(['literal']);
});

it('returns everything for a blank term', function () {
    Snippet::factory()->count(3)->create();

    expect(Snippet::query()->search(null)->count())->toBe(3)
        ->and(Snippet::query()->search('  ')->count())->toBe(3);
});

it('filters by language', function () {
    Snippet::factory()->create(['language' => Language::Php]);
    Snippet::factory()->create(['language' => Language::Python]);

    expect(Snippet::query()->forLanguage(Language::Php)->count())->toBe(1)
        ->and(Snippet::query()->forLanguage(null)->count())->toBe(2);
});

it('orders by most recently updated', function () {
    $old = Snippet::factory()->create(['title' => 'old']);
    $new = Snippet::factory()->create(['title' => 'new']);

    $old->forceFill(['updated_at' => now()->subDay()])->save();

    expect(Snippet::query()->recent()->pluck('title')->all())->toBe(['new', 'old']);
});
```

The wildcard-escaping test matters: an unescaped `%` in a search box silently matches everything, which reads as "search is broken" rather than as a bug.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Models/SnippetSearchTest.php`
Expected: FAIL — undefined scope.

- [ ] **Step 3: Add the scopes to `Snippet`**

```php
/** @param  Builder<self>  $query */
public function scopeSearch(Builder $query, ?string $term): void
{
    $term = trim((string) $term);

    if ($term === '') {
        return;
    }

    $escaped = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

    $query->where(function (Builder $query) use ($escaped): void {
        $query->whereRaw('lower(title) like lower(?) escape ?', [$escaped, '\\'])
            ->orWhereRaw('lower(body) like lower(?) escape ?', [$escaped, '\\']);
    });
}

/** @param  Builder<self>  $query */
public function scopeForLanguage(Builder $query, ?Language $language): void
{
    if ($language instanceof Language) {
        $query->where('language', $language->value);
    }
}

/** @param  Builder<self>  $query */
public function scopeRecent(Builder $query): void
{
    $query->orderByDesc('updated_at');
}
```

Add `use Illuminate\Database\Eloquent\Builder;`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Models/SnippetSearchTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Models/Snippet.php tests/
git commit -m "feat: add search, language filter and recency scopes"
```

---

### Task 10: Form requests and the size cap

**Files:**
- Create: `app/Http/Requests/StoreSnippetRequest.php`, `app/Http/Requests/UpdateSnippetRequest.php`
- Test: `tests/Feature/Requests/SnippetRequestTest.php`

**Interfaces:**
- Produces: `StoreSnippetRequest::rules(): array`, `UpdateSnippetRequest::rules(): array`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\Language;
use App\Http\Requests\StoreSnippetRequest;
use Illuminate\Support\Facades\Validator;

function validateSnippet(array $data): Illuminate\Contracts\Validation\Validator
{
    return Validator::make($data, (new StoreSnippetRequest)->rules());
}

it('accepts a minimal snippet', function () {
    expect(validateSnippet(['body' => 'echo 1;', 'language' => Language::Php->value])->passes())->toBeTrue();
});

it('accepts a null title', function () {
    expect(validateSnippet(['title' => null, 'body' => 'x', 'language' => 'php'])->passes())->toBeTrue();
});

it('requires a body', function () {
    expect(validateSnippet(['body' => '', 'language' => 'php'])->passes())->toBeFalse();
});

it('rejects a body over 100 KB', function () {
    expect(validateSnippet(['body' => str_repeat('a', 102401), 'language' => 'php'])->passes())->toBeFalse();
});

it('accepts a body at exactly 100 KB', function () {
    expect(validateSnippet(['body' => str_repeat('a', 102400), 'language' => 'php'])->passes())->toBeTrue();
});

it('rejects a language outside the allowlist', function () {
    expect(validateSnippet(['body' => 'x', 'language' => 'cobol'])->passes())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Requests/SnippetRequestTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Generate and write the requests**

```bash
php artisan make:request StoreSnippetRequest --no-interaction
php artisan make:request UpdateSnippetRequest --no-interaction
```

Both share these rules — repeat them in each class rather than sharing a trait; there are only two and they will diverge:

```php
/** @return array<string, array<int, mixed>> */
public function rules(): array
{
    return [
        'title' => ['nullable', 'string', 'max:255'],
        'body' => ['required', 'string', 'max:102400'],
        'language' => ['required', Rule::enum(Language::class)],
    ];
}

/** @return array<string, string> */
public function messages(): array
{
    return [
        'body.required' => 'A snippet needs some code.',
        'body.max' => 'Snippets are limited to 100 KB.',
    ];
}
```

Set `authorize(): bool` to `return true;` in both — there are no users.

Add `use App\Enums\Language;` and `use Illuminate\Validation\Rule;`.

Note `max:102400` on a string counts **characters**, not bytes. That's close enough for the guard's purpose (preventing a hang) and is simpler than a byte-exact rule.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Requests/SnippetRequestTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/ tests/
git commit -m "feat: validate snippets with a 100 KB body cap"
```

---

### Task 11: Export and import

**Files:**
- Create: `app/Support/SnippetArchive.php`
- Test: `tests/Feature/SnippetArchiveTest.php`

**Interfaces:**
- Consumes: `Snippet`, `Language`.
- Produces: `SnippetArchive::export(): string`, `SnippetArchive::import(string $json): int`.

**Why the shape is lopsided:** export goes out through `Share::file()`, but there is **no file picker** on this platform — `File` offers only `move()` and `copy()`, and the marketplace has no document-picker plugin. So import is a paste box fed by `Clipboard::readText()`. Renders are deliberately not exported; they are derived and get rebuilt on import.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Enums\Language;
use App\Models\Snippet;
use App\Support\SnippetArchive;

beforeEach(function () {
    $this->archive = app(SnippetArchive::class);
});

it('exports every snippet as json', function () {
    Snippet::factory()->create(['title' => 'One', 'body' => 'a', 'language' => Language::Php]);
    Snippet::factory()->create(['title' => 'Two', 'body' => 'b', 'language' => Language::Go]);

    $decoded = json_decode($this->archive->export(), true);

    expect($decoded['version'])->toBe(1)
        ->and($decoded['snippets'])->toHaveCount(2)
        ->and($decoded['snippets'][0])->toHaveKeys(['title', 'body', 'language', 'created_at', 'updated_at']);
});

it('does not export derived renders', function () {
    $snippet = Snippet::factory()->create();
    $snippet->renders()->create(['theme' => 'light', 'content' => [], 'hash' => 'x']);

    expect($this->archive->export())->not->toContain('renders');
});

it('round-trips an export back into an empty database', function () {
    Snippet::factory()->create(['title' => 'Kept', 'body' => 'keep me', 'language' => Language::Rust]);
    $json = $this->archive->export();

    Snippet::query()->delete();
    $imported = $this->archive->import($json);

    expect($imported)->toBe(1)
        ->and(Snippet::query()->first()->title)->toBe('Kept')
        ->and(Snippet::query()->first()->language)->toBe(Language::Rust);
});

it('adds to existing snippets rather than replacing them', function () {
    Snippet::factory()->create(['title' => 'Existing']);
    $json = json_encode(['version' => 1, 'snippets' => [
        ['title' => 'Incoming', 'body' => 'x', 'language' => 'php', 'created_at' => null, 'updated_at' => null],
    ]]);

    $this->archive->import($json);

    expect(Snippet::query()->count())->toBe(2);
});

it('rejects malformed json', function () {
    expect(fn () => $this->archive->import('not json'))->toThrow(InvalidArgumentException::class);
});

it('rejects an unknown archive version', function () {
    expect(fn () => $this->archive->import(json_encode(['version' => 99, 'snippets' => []])))
        ->toThrow(InvalidArgumentException::class);
});

it('skips entries with an unknown language rather than aborting', function () {
    $json = json_encode(['version' => 1, 'snippets' => [
        ['title' => 'Good', 'body' => 'x', 'language' => 'php', 'created_at' => null, 'updated_at' => null],
        ['title' => 'Bad', 'body' => 'y', 'language' => 'cobol', 'created_at' => null, 'updated_at' => null],
    ]]);

    expect($this->archive->import($json))->toBe(1)
        ->and(Snippet::query()->count())->toBe(1);
});
```

The "adds rather than replaces" test encodes a deliberate choice: an import must never be able to destroy the library it is merging into.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/SnippetArchiveTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the archive**

```php
<?php

namespace App\Support;

use App\Enums\Language;
use App\Models\Snippet;
use InvalidArgumentException;

final class SnippetArchive
{
    private const VERSION = 1;

    public function export(): string
    {
        $snippets = Snippet::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Snippet $snippet): array => [
                'title' => $snippet->title,
                'body' => $snippet->body,
                'language' => $snippet->language->value,
                'created_at' => $snippet->created_at?->toIso8601String(),
                'updated_at' => $snippet->updated_at?->toIso8601String(),
            ])
            ->all();

        return json_encode(
            ['version' => self::VERSION, 'snippets' => $snippets],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    public function import(string $json): int
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! isset($decoded['snippets']) || ! is_array($decoded['snippets'])) {
            throw new InvalidArgumentException('That does not look like a Codepad backup.');
        }

        if (($decoded['version'] ?? null) !== self::VERSION) {
            throw new InvalidArgumentException('That backup was made by an incompatible version of Codepad.');
        }

        $imported = 0;

        foreach ($decoded['snippets'] as $entry) {
            $language = Language::tryFrom($entry['language'] ?? '');

            if (! $language instanceof Language || ! isset($entry['body'])) {
                continue;
            }

            Snippet::query()->create([
                'title' => $entry['title'] ?? null,
                'body' => $entry['body'],
                'language' => $language,
            ]);

            $imported++;
        }

        return $imported;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/SnippetArchiveTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add app/Support/SnippetArchive.php tests/
git commit -m "feat: add JSON export and additive import"
```

---

### Task 12: Migration-safety regression test

**Files:**
- Test: `tests/Feature/MigrationSafetyTest.php`

**Why:** the docs are explicit — migrations run on **every app start**, and *"You don't want to accidentally delete your user's data when they update your app."* Since there is no backend, a destructive migration shipped in an update is the single most likely way you personally destroy the user's library. This test is the guard.

- [ ] **Step 1: Write the test**

```php
<?php

use App\Enums\Language;
use App\Models\Snippet;
use Illuminate\Support\Facades\Artisan;

it('preserves existing rows when migrations are re-run', function () {
    $snippet = Snippet::query()->create([
        'title' => 'Survivor',
        'body' => '<?php echo "still here";',
        'language' => Language::Php,
    ]);

    Artisan::call('migrate', ['--force' => true]);

    expect(Snippet::query()->count())->toBe(1)
        ->and(Snippet::query()->find($snippet->id)?->title)->toBe('Survivor');
});

it('has no migration that drops or truncates a data table', function () {
    $offenders = collect(File::files(database_path('migrations')))
        ->filter(function (SplFileInfo $file): bool {
            $contents = file_get_contents($file->getPathname());

            return str_contains($contents, 'dropIfExists(\'snippets\'')
                || str_contains($contents, 'dropIfExists(\'snippet_renders\'')
                || str_contains($contents, 'truncate(');
        })
        ->map(fn (SplFileInfo $file): string => $file->getFilename())
        ->values();

    expect($offenders)->toBeEmpty("Destructive migration(s): {$offenders->implode(', ')}");
});
```

Add `use Illuminate\Support\Facades\File;` and `use Symfony\Component\Finder\SplFileInfo;`.

The second test will fail the moment someone adds a `dropIfExists` in a new migration — which is exactly when you want to be stopped. The `down()` methods of the original create-migrations are the expected exception; if they trip it, narrow the check to migrations dated after your 1.0 release.

- [ ] **Step 2: Run the test**

Run: `php artisan test tests/Feature/MigrationSafetyTest.php`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
vendor/bin/pint --dirty
git add tests/Feature/MigrationSafetyTest.php
git commit -m "test: guard against destructive migrations reaching users"
```

---

### Task 13: Prune the bundle

**Files:**
- Modify: `config/nativephp.php`

**Why:** Phiki is ~13.2 MB in `vendor` — enough that it [broke Vapor deploys in Laravel 12.29](https://github.com/laravel/framework/issues/57117). You use 16 of its 200+ grammars and 2 of its 50+ themes. Everything else is dead weight in the app bundle.

- [ ] **Step 1: Find the grammar and theme directories**

```bash
du -sh vendor/phiki/phiki/*
ls vendor/phiki/phiki/resources 2>/dev/null || find vendor/phiki/phiki -maxdepth 2 -type d
```

- [ ] **Step 2: Add exclusions to `cleanup_exclude_files` in `config/nativephp.php`**

Exclude every grammar file except the 16 backing `Language`, and every theme except `github-light` and `github-dark`. Follow the existing array's pattern (read the surrounding config comments for the expected glob syntax).

- [ ] **Step 3: Verify the app still highlights after a production build**

Build for a device and open a snippet in each of the 16 languages. **Deleting a grammar Phiki still references is a runtime error, not a build error** — it will only surface when a user opens that language. Check all 16 by hand.

- [ ] **Step 4: Record the before/after bundle size in the commit message**

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty
git add config/nativephp.php
git commit -m "chore: prune unused phiki grammars and themes from the app bundle"
```

---

## Phase 2 — Screens

**Read this before starting Task 14.** These tasks specify behaviour and leave exact component/namespace names to be confirmed against the installed package. The v4 docs show both `Native\Mobile\Edge\Elements\*` and `Native\Mobile\UI\Elements\*`; this plan has verified neither. Read `vendor/nativephp/mobile/src/` and https://nativephp.com/docs/mobile/4/the-basics/routing first, then fix the imports here.

Screens are testable off-device — the docs document `->search('query')`, `->searchResults()` and interaction helpers. Use them; do not defer all screen testing to manual device checks.

### Task 14: Snippet list screen

**Files:**
- Create: `app/Native/SnippetListScreen.php`, `resources/views/native/snippets/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Screens/SnippetListScreenTest.php`

**Interfaces:**
- Consumes: `Snippet::search()`, `Snippet::forLanguage()`, `Snippet::recent()`, `Snippet::displayTitle()`.
- Produces: route `/`, public properties `$search` (string) and `$language` (?string).

**Behaviour:**
- Flat list, most recently updated first. No folders, no tags.
- A `<native:bare-text-input>` bound with `native:model.debounce.300ms="search"` filters the list. **Not** `NavBar::searchBar` — its documented results are plain strings and each row needs title, language and a code preview.
- A horizontal `<native:scroll-view axis="horizontal">` of language chips filters by language; tapping the active chip clears it.
- Each row: `displayTitle()`, language label, first ~2 lines of body as preview, relative updated time.
- Empty states are distinct: "No snippets yet" (nothing saved) vs "No matches" (filters active) — with a way to clear filters in the second.
- Use `<native:list>`. Switch to `<native:virtual-list>` only if the list visibly stutters; it is limited to one per screen and adds a windowing trait.

- [ ] **Step 1: Write the failing feature test** covering: empty state, rows appear newest-first, search narrows results, language chip narrows results, search plus chip combine, clearing search restores all.
- [ ] **Step 2: Run it and confirm it fails**
- [ ] **Step 3: Implement the screen class and Blade view**
- [ ] **Step 4: Run the test to green**
- [ ] **Step 5: Check on a device** — the list is the app's front door
- [ ] **Step 6: `vendor/bin/pint --dirty` and commit**

---

### Task 15: Snippet read screen

**Files:**
- Create: `app/Native/SnippetShowScreen.php`, `resources/views/native/snippets/show.blade.php`, `resources/views/native/partials/highlighted-code.blade.php`
- Test: `tests/Feature/Screens/SnippetShowScreenTest.php`

**Interfaces:**
- Consumes: `SnippetRenderer::renderFor()`, `HighlightedCode::toArray()`, `HighlightedCode::truncate()`, `Clipboard::writeText()`, `Share::file()`.

**Behaviour:**
- The partial renders `HighlightedCode` as one `<native:text class="font-mono select-text">` containing nested `<native:text color="...">` runs, one group per line.
- `select-text` is required — it is the OS-level long-press copy path and the fallback if the clipboard plugin misbehaves.
- **Soft wrap.** No horizontal scroll in v1.
- `renderFor()` returning `null` (no render yet, or a stale hash) renders the raw body as plain mono text. This is a normal state, not an error — never show an error for it.
- Truncate at 300 lines with a "Show all N lines" action, unless Task 0.1 cleared the cap.
- Primary action: **Copy** via `Clipboard::writeText($snippet->body)`, with haptic or toast confirmation. Copy the **raw body**, never the rendered runs.
- Secondary: **Share** via `Share::file($snippet->displayTitle(), $snippet->body, '')` — the documented text-only form.
- Also: Edit, Delete (with confirm), and a language chip that opens the picker and triggers `SnippetRenderer::refresh()`.

- [ ] **Step 1: Write the failing test** covering: highlighted runs render, missing render falls back to plain body, stale hash falls back to plain body, truncation at 300 lines, copy writes the raw body (use the plugin's `assertCopied()` helper), changing language refreshes renders.
- [ ] **Step 2: Run it and confirm it fails**
- [ ] **Step 3: Implement the partial, then the screen**
- [ ] **Step 4: Run the test to green**
- [ ] **Step 5: Check on a device** with a long, wide snippet
- [ ] **Step 6: `vendor/bin/pint --dirty` and commit**

---

### Task 16: Snippet edit screen and the save pipeline

**Files:**
- Create: `app/Native/SnippetEditScreen.php`, `resources/views/native/snippets/edit.blade.php`
- Test: `tests/Feature/Screens/SnippetEditScreenTest.php`

**Interfaces:**
- Consumes: `StoreSnippetRequest`/`UpdateSnippetRequest` rules, `SnippetRenderer::refresh()`.

**Behaviour:**
- A `multiline` text input with `min-lines`, using a bundled monospace font via the `font` prop. Put a mono `.ttf` in `resources/fonts/` and register it in `config/native-ui.php` — `<native:text>`'s `font-mono` class does not apply to text inputs.
- **No highlighting while editing.** `<native:*-text-input>` has no span support; this is a platform limit, not a shortcut.
- Optional title field, placeholder showing the derived fallback so the user sees what the list will show.
- Language picker defaulting to the **last-used language** (persist it — a small key/value store or a cache entry; do not add a settings table for one value).
- **Save order matters:** persist body/title/language first, return to the read screen, *then* refresh renders. The user must never wait on highlighting. If Task 0.1 measured over 300 ms, dispatch the refresh rather than running it inline.
- Validation errors surface inline; the 100 KB cap shows "Snippets are limited to 100 KB."

- [ ] **Step 1: Write the failing test** covering: saving persists changes, saving refreshes both renders, changing only the title does *not* invalidate renders, an over-cap body is rejected, the language picker defaults to last-used.
- [ ] **Step 2: Run it and confirm it fails**
- [ ] **Step 3: Implement**
- [ ] **Step 4: Run the test to green**
- [ ] **Step 5: Check on a device** — specifically whether the keyboard applies autocorrect or auto-capitalisation to code. The `keyboard` prop offers no "no autocorrect" option; if it mangles input, try `keyboard="url"` (usually the least interfering) and record the result here.
- [ ] **Step 6: `vendor/bin/pint --dirty` and commit**

---

### Task 17: Capture from clipboard

**Files:**
- Modify: `app/Native/SnippetListScreen.php`, `resources/views/native/snippets/index.blade.php`
- Test: `tests/Feature/Screens/CaptureFlowTest.php`

**Interfaces:**
- Consumes: `Clipboard::readText()`, `SnippetEditScreen`.

**Behaviour — this is the flow the whole app is arranged around:**

FAB on the list → new snippet screen with the body **pre-filled from `Clipboard::readText()`** → language defaults to last-used → save.

- Empty or null clipboard opens an empty editor. Never show an error.
- **Apply Task 0.2's finding.** If iOS prompts for paste consent on every read, do *not* auto-fill — render an explicit "Paste from clipboard" button so the prompt follows a deliberate tap. Record which behaviour you shipped.
- Nothing on this path may block: no spinner, no highlight, no network.

- [ ] **Step 1: Write the failing test** using `withClipboard('...')` to cover: clipboard content pre-fills the body, empty clipboard yields an empty editor, the last-used language is preselected.
- [ ] **Step 2: Run it and confirm it fails**
- [ ] **Step 3: Implement**
- [ ] **Step 4: Run the test to green**
- [ ] **Step 5: Time the real flow on a device** — copy in a browser, switch apps, save. If it exceeds about five seconds, something on this path is doing work it shouldn't.
- [ ] **Step 6: `vendor/bin/pint --dirty` and commit**

---

### Task 18: Settings screen — export, import, theme

**Files:**
- Create: `app/Native/SettingsScreen.php`, `resources/views/native/settings.blade.php`
- Test: `tests/Feature/Screens/SettingsScreenTest.php`

**Interfaces:**
- Consumes: `SnippetArchive::export()`, `SnippetArchive::import()`, `Share::file()`, `Clipboard::readText()`, `SnippetRenderer::refresh()`.

**Behaviour:**
- **Export all** → write `SnippetArchive::export()` to `storage_path('app/codepad-backup.txt')` → `Share::file('Codepad backup', 'Codepad snippet backup', $path)`. Use `.txt`: the Share plugin's supported document types are pdf and txt only, and `.json` is not among them.
- **Import** → a paste box filled by `Clipboard::readText()` → `SnippetArchive::import()` → report "Imported N snippets". Import is additive and can never delete existing snippets; say so in the UI.
- After import, refresh renders for the imported snippets — they arrive without any.
- `InvalidArgumentException` from import surfaces as its message, which is already human-readable.
- Theme toggle: light / dark / follow system. Both renders already exist for every snippet, so switching is a read, not a recompute.
- **If Task 0.3 found the database is excluded from OS backup**, put a prominent notice here — export is then the only backup that exists.

- [ ] **Step 1: Write the failing test** covering: export produces valid JSON for all snippets, import adds without deleting, malformed input shows a readable error, imported snippets get renders.
- [ ] **Step 2: Run it and confirm it fails**
- [ ] **Step 3: Implement**
- [ ] **Step 4: Run the test to green**
- [ ] **Step 5: Full round-trip on a device** — export, share to Files, uninstall, reinstall, paste back, confirm every snippet returns with correct highlighting
- [ ] **Step 6: `vendor/bin/pint --dirty` and commit**

---

## Definition of done for v1

- [ ] All three Phase 0 spikes run, with results recorded in this document
- [ ] `php artisan test` green
- [ ] `vendor/bin/pint --test` clean
- [ ] Capture → save → find → copy works end-to-end on a physical iPhone **and** a physical Android device
- [ ] Export → uninstall → reinstall → import returns every snippet
- [ ] All 16 languages verified to still highlight after bundle pruning
- [ ] App identity set; no placeholder values remain in `.env.example`

## Explicitly out of scope for v1

Language auto-detection (the picker ships first; detection is a scored heuristic added later, defaulting to last-used on low confidence) · tags · folders · FTS5 · horizontal-scroll toggle for wide code · sync or any backend · accounts · deriving titles from token scopes · syntax highlighting while editing · line numbers · a keyboard accessory bar.

## Decisions worth not relitigating

These were settled deliberately; each has a reason that isn't obvious from the code.

| Decision | Why |
|---|---|
| Capture tool, not editor | `<native:text-input>` cannot render styled spans. Live highlighting means embedding CodeMirror in a WebView — abandoning the reason SuperNative was chosen. |
| Highlight on save, not on view | The read view is opened constantly; tokenising TextMate grammars in an embedded PHP runtime is not free. |
| Renders in their own table | Keeps large text columns out of the hot list query; "rebuild everything" is a truncate; a third theme is a row, not a migration. |
| `LIKE`, not FTS5 | FTS5 availability in the embedded runtime is undocumented. Don't let an unverifiable unknown gate v1. |
| Own search UI, not `NavBar::searchBar` | Its documented results are plain strings; rows need title, language and preview. |
| Search-first, no tags | Tags demand discipline at capture time — the exact moment optimised for speed. Personal tag vocabularies rot. |
| Title nullable, fallback computed | Keeping user intent separate from our guess is what makes the guess improvable later. |
| Export `.txt`, import via paste | The Share plugin supports pdf/txt only, and no file picker exists on this platform. |
