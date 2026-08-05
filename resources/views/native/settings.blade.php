<native:top-bar title="Settings" display-mode="large" />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 px-4 py-4">

        @if ($status)
            <native:text class="w-full px-4 py-3 rounded-2xl bg-theme-success/15 text-theme-success">{{ $status }}</native:text>
        @endif

        @if ($problem)
            <native:text class="w-full px-4 py-3 rounded-2xl bg-theme-destructive/15 text-theme-destructive">{{ $problem }}</native:text>
        @endif

        <native:column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface">
            <native:text class="text-lg font-semibold text-theme-on-surface">Appearance</native:text>
            <native:text class="text-sm text-theme-on-surface-variant">Both themes are rendered when a snippet is saved, so switching is instant.</native:text>

            <native:row class="w-full gap-2 pt-1">
                @foreach ($themes as $value => $label)
                    <native:chip
                        label="{{ $label }}"
                        :selected="$theme === $value"
                        @change="chooseTheme('{{ $value }}')" />
                @endforeach
            </native:row>
        </native:column>

        <native:column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface">
            <native:text class="text-lg font-semibold text-theme-on-surface">Back up your library</native:text>
            <native:text class="text-sm text-theme-on-surface-variant">Codepad keeps {{ $snippetCount }} {{ Str::plural('snippet', $snippetCount) }} on this device only — there is no account and no server. An export is the only copy that survives losing the phone.</native:text>

            <native:button label="Export all snippets" class="mt-1" @tap="export" />
        </native:column>

        <native:column class="w-full gap-2 p-4 rounded-2xl bg-theme-surface">
            <native:text class="text-lg font-semibold text-theme-on-surface">Restore from a backup</native:text>
            <native:text class="text-sm text-theme-on-surface-variant">Importing adds to your library and never deletes anything already in it.</native:text>

            <native:bare-text-input
                native:model.blur="backup"
                multiline
                :min-lines="4"
                :max-lines="8"
                font="mono"
                placeholder="Paste a backup here"
                class="w-full px-4 py-3 rounded-2xl bg-theme-surface-variant text-theme-on-surface" />

            <native:row class="w-full gap-2">
                <native:button label="Paste from clipboard" variant="secondary" @tap="pasteBackup" />
                <native:spacer />
                <native:button label="Import" @tap="import" />
            </native:row>
        </native:column>

    </native:column>
</native:scroll-view>