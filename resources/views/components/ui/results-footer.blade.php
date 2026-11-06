@props(['items'])
@if ($items->total())
    <footer class="pm-results-footer">
        <span>{{ __('Menampilkan :from–:to dari :total hasil', ['from' => $items->firstItem() ?? 0, 'to' => $items->lastItem() ?? 0, 'total' => $items->total()]) }}</span>
        @if ($items->hasPages()){{ $items->onEachSide(1)->links() }}@endif
    </footer>
@endif
