<native:top-bar title="{{ $snippet->displayTitle() }}" back="true">
    <native:top-bar-action id="edit" label="Edit" icon="edit" @tap="edit" />
</native:top-bar>

<native:column class="w-full h-full bg-theme-background">
    <native:row class="w-full items-center gap-2 px-4 py-2">
        <native:select
            :options="$languages"
            :value="$snippet->language->label()"
            a11y-label="Language"
            @change="changeLanguage" />
        <native:spacer />
        <native:text class="text-xs text-theme-on-surface-variant">{{ $totalLines }} {{ Str::plural('line', $totalLines) }}</native:text>
    </native:row>

    <native:scroll-view class="flex-1 w-full px-4">
        @if ($markdown !== null)
            <native:column class="w-full gap-3">
                @foreach ($markdown as $block)
                    @if ($block['type'] === 'heading')
                        <native:text
                            :text="$block['text']"
                            class="w-full {{ match ($block['level']) {
                                1 => 'text-3xl font-bold text-theme-on-background',
                                2 => 'text-2xl font-bold text-theme-on-background',
                                3 => 'text-xl font-bold text-theme-on-background',
                                4 => 'text-lg font-bold text-theme-on-background',
                                5 => 'text-base font-bold text-theme-on-background',
                                default => 'text-sm font-bold text-theme-on-background',
                            } }}" />
                    @elseif ($block['type'] === 'list')
                        <native:column class="w-full gap-1 pl-4">
                            @foreach ($block['items'] as $item)
                                <native:row class="w-full items-start gap-2">
                                    <native:text
                                        :text="$block['ordered'] ? $loop->iteration.'.' : '•'"
                                        class="text-base font-semibold text-theme-primary" />
                                    <native:text :text="$item['text']" class="flex-1 text-base text-theme-on-surface" />
                                </native:row>
                            @endforeach
                        </native:column>
                    @elseif ($block['type'] === 'table')
                        <native:column class="w-full rounded-xl bg-theme-surface overflow-hidden">
                            <native:row class="w-full bg-theme-surface-variant">
                                @foreach ($block['headers'] as $cell)
                                    <native:text
                                        :text="$cell['text']"
                                        class="flex-1 px-3 py-2 text-sm font-bold text-theme-on-surface {{ $cell['align'] === 'right' ? 'text-right' : ($cell['align'] === 'center' ? 'text-center' : '') }}" />
                                @endforeach
                            </native:row>
                            @foreach ($block['rows'] as $row)
                                <native:row class="w-full border-t border-theme-outline-variant">
                                    @foreach ($row as $cell)
                                        <native:text
                                            :text="$cell['text']"
                                            class="flex-1 px-3 py-2 text-sm text-theme-on-surface {{ $cell['align'] === 'right' ? 'text-right' : ($cell['align'] === 'center' ? 'text-center' : '') }}" />
                                    @endforeach
                                </native:row>
                            @endforeach
                        </native:column>
                    @elseif ($block['type'] === 'code')
                        <native:text
                            :text="$block['text']"
                            font="mono"
                            class="w-full px-3 py-2 rounded-xl bg-theme-surface text-sm text-theme-on-surface" />
                    @else
                        <native:text :text="$block['text']" class="w-full text-base text-theme-on-surface" />
                    @endif
                @endforeach
            </native:column>
        @else
            @include('native.partials.highlighted-code', ['highlighted' => $highlighted, 'body' => $body])
        @endif

        @if ($truncated)
            <native:button
                label="Show all {{ $totalLines }} lines"
                variant="secondary"
                class="my-4"
                @tap="showEverything" />
        @endif
    </native:scroll-view>

    <native:row class="w-full items-center gap-2 px-4 py-3 bg-theme-surface">
        <native:button label="Copy" icon="content_copy" class="flex-1" @tap="copy" />
        <native:button label="Share" icon="share" variant="secondary" @tap="share" />
        <native:button label="Delete" icon="delete" variant="destructive" @tap="confirmDelete" />
    </native:row>
</native:column>

<native:modal :visible="$confirmingDelete" @dismiss="cancelDelete">
    <native:column class="w-full gap-3 p-6">
        <native:text class="text-lg font-semibold text-theme-on-surface">Delete this snippet?</native:text>
        <native:text class="text-theme-on-surface-variant">This cannot be undone, and Codepad has no backup other than the one you export yourself.</native:text>
        <native:row class="w-full gap-2 justify-end">
            <native:button label="Cancel" variant="secondary" @tap="cancelDelete" />
            <native:button label="Delete" variant="destructive" @tap="delete" />
        </native:row>
    </native:column>
</native:modal>
