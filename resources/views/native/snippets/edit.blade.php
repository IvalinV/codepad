<native:top-bar title="{{ $isNew ? 'New snippet' : 'Edit snippet' }}" back="true">
    <native:top-bar-action id="save" label="Save" icon="check" @tap="save" />
</native:top-bar>

<native:column class="w-full h-full gap-3 bg-theme-background">
    <native:column class="w-full gap-1 px-4 pt-3">
        <native:bare-text-input
            native:model.blur="title"
            placeholder="{{ $titlePlaceholder }}"
            class="w-full px-4 py-3 rounded-2xl bg-theme-surface text-theme-on-surface" />

        @if ($this->errorFor('title'))
            <native:text class="text-xs px-1 text-theme-destructive">{{ $this->errorFor('title') }}</native:text>
        @endif
    </native:column>

    <native:row class="w-full items-center px-4">
        <native:select
            :options="$languages"
            :value="$languageLabel"
            @change="changeLanguage" />
    </native:row>

    <native:column class="flex-1 w-full gap-1 px-4">
        <native:bare-text-input
            native:model.blur="body"
            multiline
            :min-lines="$minLines"
            keyboard="url"
            font="mono"
            placeholder="Paste or type your code"
            class="flex-1 w-full px-4 py-3 rounded-2xl bg-theme-surface text-theme-on-surface" />

        @if ($this->errorFor('body'))
            <native:text class="text-xs px-1 text-theme-destructive">{{ $this->errorFor('body') }}</native:text>
        @endif
    </native:column>

    <native:row class="w-full items-center gap-2 px-4 py-3 bg-theme-surface">
        <native:button label="Cancel" variant="secondary" @tap="cancel" />
        <native:spacer />
        <native:button label="{{ $isNew ? 'Save snippet' : 'Save changes' }}" @tap="save" />
    </native:row>
</native:column>