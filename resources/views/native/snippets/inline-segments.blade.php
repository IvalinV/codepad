<native:row class="w-full flex-wrap items-center">
    @foreach ($segments as $segment)
        @if ($segment['type'] === 'link')
            <native:column class="{{ $textClass }}">
                <native:pressable @tap="openUrl('{{ $segment['url'] }}')">
                    <native:text
                        :text="$segment['text']"
                        class="text-theme-primary underline" />
                </native:pressable>
                @if (! empty($segment['preview']))
                    <native:column class="w-full my-2 p-3 rounded-xl bg-theme-surface">
                        @if (! empty($segment['preview']['image']))
                            <native:image
                                src="{{ $segment['preview']['image'] }}"
                                alt="{{ $segment['preview']['title'] ?? 'Link preview' }}"
                                class="w-full h-32 rounded-lg" />
                        @endif
                        @if (! empty($segment['preview']['title']))
                            <native:text
                                :text="$segment['preview']['title']"
                                class="text-base font-semibold text-theme-on-surface" />
                        @endif
                        @if (! empty($segment['preview']['description']))
                            <native:text
                                :text="$segment['preview']['description']"
                                class="text-sm text-theme-on-surface-variant" />
                        @endif
                        @if (! empty($segment['preview']['siteName']))
                            <native:text
                                :text="$segment['preview']['siteName']"
                                class="text-xs text-theme-on-surface-variant" />
                        @endif
                    </native:column>
                @endif
            </native:column>
        @else
            <native:text :text="$segment['text']" class="{{ $textClass }}" />
        @endif
    @endforeach
</native:row>
