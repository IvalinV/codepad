@php
    /*
     * Every run is passed through the `text` ATTRIBUTE rather than as tag
     * content. The element collector treats content between tags as prose —
     * it collapses runs of whitespace to a single space and drops
     * whitespace-only segments as formatting — which would silently eat the
     * indentation that makes code readable. Attributes arrive verbatim.
     *
     * Line breaks are explicit runs for the same reason: the outer <text> is
     * one selectable block (`select-text` is the OS long-press copy path, and
     * the fallback if the clipboard plugin ever misbehaves), so the lines have
     * to be joined inside it rather than stacked as separate elements.
     *
     * The runs are written as PAIRED tags with empty content, never as
     * self-closing ones. Only a paired <native:text> reaches the collector's
     * inline-run path (`textOpen`/`textClose`); the self-closing spelling
     * compiles to `leaf()`, which appends the run to the nearest CONTAINER
     * instead — so the runs escape the block entirely and stack one token per
     * line down the scroll view, while the outer <text> emits empty. That
     * failure is invisible to `assertSee`, so it is pinned by a tree-shape
     * test in SnippetShowScreenTest rather than by a content assertion.
     */
    $newline = "\n";
@endphp

@if ($highlighted === null)
    <native:text class="w-full font-mono text-sm select-text text-theme-on-surface" :text="$body" />
@else
    <native:text class="w-full font-mono text-sm select-text">
        @foreach ($highlighted->toArray() as $index => $runs)
            @if ($index > 0)
                <native:text :text="$newline"></native:text>
            @endif

            @foreach ($runs as $run)
                <native:text :text="$run['text']" :color="$run['color']"></native:text>
            @endforeach
        @endforeach
    </native:text>
@endif
