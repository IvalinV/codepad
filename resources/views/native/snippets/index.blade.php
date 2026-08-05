{{-- Settings is a tab in TabsLayout, so it is deliberately not an action here. --}}
<native:top-bar title="Codepad" display-mode="large" />

<native:column class="w-full h-full gap-3 bg-theme-background">
    <native:bare-text-input
        native:model.debounce.300ms="search"
        placeholder="Search snippets"
        class="mx-4 mt-3 px-5 py-3 rounded-full bg-theme-surface text-theme-on-surface" />

    {{--
        Absent, not empty, when there is nothing to choose between: an empty
        scroll view still takes a slot in this column's `gap-3` and pushes the
        list down for no reason. The screen decides when that is — see
        SnippetListScreen::languageFilters().
    --}}
    @if ($languages !== [])
        <native:scroll-view axis="horizontal" shows-indicators="false" class="w-full gap-1 px-2">
            @foreach ($languages as $option)
                <native:row class="w-full gap-1 px-2">
                    <native:chip
                        label="{{ $option->label() }}"
                        :selected="$language === $option->value"
                        @change="toggleLanguage('{{ $option->value }}')" />
                </native:row>
            @endforeach
        </native:scroll-view>
    @endif

    @if ($snippets->isEmpty())
        <native:column class="flex-1 w-full items-center justify-center gap-2 px-8">
            @if ($libraryIsEmpty)
                <native:text class="text-lg font-semibold text-theme-on-background">No snippets yet</native:text>
                <native:text class="text-center text-theme-on-surface-variant">Copy some code, then tap Capture to keep it.</native:text>
            @else
                <native:text class="text-lg font-semibold text-theme-on-background">No matches</native:text>
                <native:text class="text-center text-theme-on-surface-variant">Nothing in your library matches the current filters.</native:text>
                <native:button label="Clear filters" variant="secondary" @tap="clearFilters" />
            @endif
        </native:column>
    @else
        {{--
            Rows are cards on the background rather than separated list rows:
            a snippet is an object you pick up, and the card edge is what makes
            the code preview inside it read as content rather than as more chrome.
        --}}
        <native:list plain class="flex-1 w-full">
            @foreach ($snippets as $snippet)
                <native:pressable class="w-full px-4 py-1" @tap="open({{ $snippet->id }})">
                    <native:column class="w-full gap-1 px-4 py-3 rounded-2xl bg-theme-surface">
                        <native:text class="font-semibold text-theme-on-surface" max-lines="1">{{ $snippet->displayTitle() }}</native:text>
                        <native:text class="text-sm text-theme-on-surface-variant" font="mono" max-lines="2">{{ $this->preview($snippet) }}</native:text>
                        <native:row class="w-full items-center gap-2 pt-1">
                            <native:text class="text-xs px-2 py-1 rounded-full bg-theme-primary/15 text-theme-primary">{{ $snippet->language->label() }}</native:text>
                            <native:spacer />
                            <native:text class="text-xs text-theme-on-surface-variant">{{ $snippet->updated_at?->diffForHumans() }}</native:text>
                        </native:row>
                    </native:column>
                </native:pressable>
            @endforeach
        </native:list>
    @endif
</native:column>

{{-- Icon-only, so the label is the only thing a screen reader has to announce. --}}
<native:fab icon="add" a11y-label="Capture a new snippet" @tap="create" />
