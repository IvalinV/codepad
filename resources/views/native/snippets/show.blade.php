<native:top-bar title="{{ $snippet->displayTitle() }}" back="true">
    <native:top-bar-action id="edit" label="Edit" icon="edit" @tap="edit" />
</native:top-bar>

<native:column class="w-full h-full bg-theme-background">
    <native:row class="w-full items-center gap-2 px-4 py-2">
        <native:select
            :options="$languages"
            :value="$snippet->language->label()"
            @change="changeLanguage" />
        <native:spacer />
        <native:text class="text-xs text-theme-on-surface-variant">{{ $totalLines }} {{ Str::plural('line', $totalLines) }}</native:text>
    </native:row>

    <native:scroll-view class="flex-1 w-full px-4">
        @include('native.partials.highlighted-code', ['highlighted' => $highlighted, 'body' => $body])

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