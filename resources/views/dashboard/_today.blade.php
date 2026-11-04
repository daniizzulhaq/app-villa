<x-ui.card :title="__('Hari ini')" :subtitle="today()->translatedFormat('l, d M')" flush>
    <x-slot:actions>
        <x-ui.badge variant="success" plain>{{ __(':count in', ['count' => $widgets['checkIns']->count()]) }}</x-ui.badge>
        <x-ui.badge variant="warning" plain>{{ __(':count out', ['count' => $widgets['checkOuts']->count()]) }}</x-ui.badge>
    </x-slot:actions>
    @if ($widgets['checkIns']->isEmpty() && $widgets['checkOuts']->isEmpty())
        <p class="px-4 py-3 text-muted fs-sm mb-0">{{ __('Tidak ada check-in atau check-out hari ini.') }}</p>
    @else
        <ul class="pm-agenda px-4">
            @foreach ($widgets['checkIns'] as $b)
                <li><span class="pm-agenda__icon pm-agenda__icon--in" aria-hidden="true"><i class="bi bi-box-arrow-in-right"></i></span>
                    <div><div class="pm-agenda__title fs-sm">{{ __('Check-in · :name', ['name' => $b->villa->name]) }}</div><div class="pm-agenda__meta">{{ $b->guest_name ?: __('Tamu') }}@if($b->arrival_time) {{ __('· jam :arrival_time', ['arrival_time' => substr($b->arrival_time, 0, 5)]) }}@endif</div></div></li>
            @endforeach
            @foreach ($widgets['checkOuts'] as $b)
                <li><span class="pm-agenda__icon pm-agenda__icon--out" aria-hidden="true"><i class="bi bi-box-arrow-right"></i></span>
                    <div><div class="pm-agenda__title fs-sm">{{ __('Check-out · :name', ['name' => $b->villa->name]) }}</div><div class="pm-agenda__meta">{{ $b->guest_name ?: __('Tamu') }}</div></div></li>
            @endforeach
        </ul>
    @endif
</x-ui.card>
