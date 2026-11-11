<native:row class="w-full flex-wrap items-center">
    @foreach ($segments as $segment)
        @if ($segment['type'] === 'link')
            <native:pressable @tap="openUrl('{{ $segment['url'] }}')">
                <native:text
                    :text="$segment['text']"
                    class="{{ $textClass }} text-theme-primary underline" />
            </native:pressable>
        @else
            <native:text :text="$segment['text']" class="{{ $textClass }}" />
        @endif
    @endforeach
</native:row>
