<native:top-bar title="Codepad" display-mode="large">
    <native:top-bar-action id="settings" label="Settings" icon="settings" @tap="openSettings" />
</native:top-bar>

<native:column class="w-full h-full gap-3 bg-theme-background">
    <native:bare-text-input
        native:model.debounce.300ms="search"
        placeholder="Search snippets"
        class="mx-4 mt-3 px-4 py-2 rounded-full bg-theme-surface-variant text-theme-on-surface" />

    <native:scroll-view axis="horizontal" shows-indicators="false" class="w-full gap-2 px-4">
        @foreach ($languages as $option)
            <native:chip
                label="{{ $option->label() }}"
                :selected="$language === $option->value"
                @change="toggleLanguage('{{ $option->value }}')" />
        @endforeach
    </native:scroll-view>

    @if ($snippets->isEmpty())
        <native:column class="flex-1 w-full items-center justify-center gap-2 px-8">
            @if ($libraryIsEmpty)
                <native:text class="text-lg font-semibold text-theme-on-background">No snippets yet</native:text>
                <native:text class="text-center text-theme-on-surface-variant">Copy some code, then tap the button below to capture it.</native:text>
            @else
                <native:text class="text-lg font-semibold text-theme-on-background">No matches</native:text>
                <native:text class="text-center text-theme-on-surface-variant">Nothing in your library matches the current filters.</native:text>
                <native:button label="Clear filters" variant="secondary" @tap="clearFilters" />
            @endif
        </native:column>
    @else
        <native:list separator class="flex-1 w-full">
            @foreach ($snippets as $snippet)
                <native:pressable class="w-full px-4 py-3" @tap="open({{ $snippet->id }})">
                    <native:column class="w-full gap-1">
                        <native:text class="font-semibold text-theme-on-surface" max-lines="1">{{ $snippet->displayTitle() }}</native:text>
                        <native:text class="text-sm font-mono text-theme-on-surface-variant" max-lines="2">{{ $this->preview($snippet) }}</native:text>
                        <native:row class="w-full gap-2">
                            <native:text class="text-xs text-theme-on-surface-variant">{{ $snippet->language->label() }}</native:text>
                            <native:text class="text-xs text-theme-on-surface-variant">{{ $snippet->updated_at?->diffForHumans() }}</native:text>
                        </native:row>
                    </native:column>
                </native:pressable>
            @endforeach
        </native:list>
    @endif
</native:column>

<native:fab icon="add" @tap="create" />