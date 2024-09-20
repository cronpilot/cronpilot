<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <pre class="overflow-auto h-96">
        <code>{{ $getState() }}</code>
    </pre>
</x-dynamic-component>
