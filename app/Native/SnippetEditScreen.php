<?php

namespace App\Native;

use App\Enums\Language;
use App\Http\Requests\StoreSnippetRequest;
use App\Http\Requests\UpdateSnippetRequest;
use App\Models\Snippet;
use App\Support\Highlighting\SnippetRenderer;
use App\Support\Preferences;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use NativePHP\Clipboard\Facades\Clipboard;

/**
 * Writing a snippet — both halves of it. With a `snippet` route parameter
 * this edits a stored one; without, it is a new capture, pre-filled from the
 * clipboard.
 *
 * There is no syntax highlighting while editing. `<native:*-text-input>` has
 * no span support, so a coloured editor is not a thing this platform can do —
 * it is a limit, not a shortcut. The bundled mono font is the compensation:
 * code lines up in the editor exactly as it will on the read screen.
 */
class SnippetEditScreen extends NativeComponent
{
    /** Rows the editor opens at, so a short snippet still looks like a canvas. */
    private const MIN_LINES = 12;

    public string $body = '';

    public string $title = '';

    /**
     * The language as its human label rather than its value: `<native:select>`
     * takes a flat list of strings and hands back the chosen one, so the label
     * is what travels over the wire in both directions.
     */
    public string $languageLabel = '';

    /** @var array<string, string> */
    public array $errors = [];

    protected ?Snippet $snippet = null;

    /**
     * Whether this screen opened as a capture rather than an edit. Read
     * after saving, when `$this->snippet` has been filled in and can no
     * longer answer the question.
     */
    protected bool $openedAsCapture = true;

    public function mount(): void
    {
        $this->snippet = Snippet::query()->find((int) $this->param('snippet'));
        $this->openedAsCapture = $this->snippet === null;

        if ($this->snippet !== null) {
            $this->title = (string) $this->snippet->title;
            $this->body = $this->snippet->body;
            $this->languageLabel = $this->snippet->language->label();

            return;
        }

        /*
         * A new snippet starts from whatever the user just copied — this is
         * the flow the whole app is arranged around. An empty or unreadable
         * clipboard opens an empty editor; it is never an error, and never
         * worth a message.
         *
         * If a device spike finds iOS prompting for paste consent on every
         * read, this line moves behind an explicit "Paste from clipboard"
         * button so the prompt follows a deliberate tap rather than
         * ambushing the user on screen open. Recorded as shipped: auto-fill.
         */
        $this->body = (string) Clipboard::readText();
        $this->languageLabel = app(Preferences::class)->lastLanguage()->label();
    }

    public function render(): View
    {
        return view('native.snippets.edit', [
            'isNew' => $this->snippet === null,
            'minLines' => self::MIN_LINES,
            'titlePlaceholder' => $this->derivedTitle(),
            'languages' => array_map(fn (Language $case): string => $case->label(), Language::cases()),
        ]);
    }

    public function changeLanguage(string $label): void
    {
        if (Language::tryFromLabel($label) instanceof Language) {
            $this->languageLabel = $label;
        }
    }

    /**
     * Persist first, then re-derive, then leave.
     *
     * The order matters and is not negotiable: the body is the thing the user
     * would lose, and highlighting is derived data that can always be rebuilt.
     * A refresh that fails or is interrupted costs colour; a save that happens
     * after it would cost the snippet.
     *
     * Renders are only re-derived when the body or the language actually
     * moved. Renaming a snippet does not change a single token, and a title
     * edit should not cost a full two-theme tokenise.
     */
    public function save(): void
    {
        $language = Language::tryFromLabel($this->languageLabel);

        $validator = Validator::make(
            [
                'title' => $this->title === '' ? null : $this->title,
                'body' => $this->body,
                'language' => $language?->value,
            ],
            $this->rules(),
            $this->messages(),
        );

        if ($validator->fails()) {
            $this->errors = $validator->errors()->toArray();

            return;
        }

        $this->errors = [];

        $attributes = $validator->validated();
        $attributes['language'] = $language;

        $needsRender = $this->snippet === null
            || $this->snippet->body !== $attributes['body']
            || $this->snippet->language !== $language;

        $this->snippet = $this->snippet === null
            ? Snippet::query()->create($attributes)
            : tap($this->snippet)->update($attributes);

        app(Preferences::class)->rememberLanguage($language);

        if ($needsRender) {
            app(SnippetRenderer::class)->refresh($this->snippet);
        }

        $this->leave();
    }

    public function cancel(): void
    {
        $this->leave();
    }

    /**
     * Capture is a TAB, so this screen can be the root of its stack — and
     * `back()` at the root pops the last frame and exits the app. Leaving a
     * capture therefore replaces onto the library, which both survives that
     * case and lands the user where their new snippet actually is. Editing
     * is always a push from the read screen, so there a pop is correct.
     */
    private function leave(): void
    {
        if ($this->openedAsCapture) {
            $this->replace('/');

            return;
        }

        $this->back();
    }

    /**
     * What the list will call this snippet if the title is left blank. Shown
     * as the title field's placeholder so the user can see the guess before
     * they accept it.
     */
    public function derivedTitle(): string
    {
        $preview = new Snippet(['body' => $this->body]);

        return $preview->displayTitle();
    }

    public function errorFor(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * The same rules the HTTP layer would apply. The two form requests are
     * deliberately kept separate because they are expected to diverge, so
     * this picks whichever one matches what is about to happen rather than
     * hardcoding either.
     *
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return $this->snippet === null
            ? (new StoreSnippetRequest)->rules()
            : (new UpdateSnippetRequest)->rules();
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return $this->snippet === null
            ? (new StoreSnippetRequest)->messages()
            : (new UpdateSnippetRequest)->messages();
    }
}
