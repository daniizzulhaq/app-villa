@props(['villa'])
<article class="pm-card pm-property">
    <div class="pm-property__cover">
        <a href="{{ route('villas.show', $villa) }}" aria-label="{{ __('Lihat :name', ['name' => $villa->name]) }}">
            @if ($villa->photoUrl())
                <img src="{{ $villa->photoUrl() }}" alt="{{ __('Foto :name', ['name' => $villa->name]) }}" loading="lazy">
            @else
                <div class="pm-property__placeholder">
                    <svg viewBox="0 0 120 100" fill="none" aria-hidden="true"><path d="M15 80h90M28 80V47l32-23 32 23v33M22 51l38-28 38 28M50 80V58h20v22M34 56h9v11h-9zm43 0h9v11h-9zM14 80V58m-7 4 7-12 7 12M106 80V53m-8 5 8-15 8 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="90" cy="18" r="7" stroke="currentColor" stroke-width="1.3"/><path d="M34 88h52" stroke="currentColor" stroke-linecap="round"/></svg>
                    <small>{{ __('Belum ada foto') }}</small>
                </div>
            @endif
        </a>
        <div class="pm-property__status"><x-ui.badge :variant="$villa->is_active ? 'success' : 'neutral'">{{ $villa->is_active ? __('Aktif') : __('Nonaktif') }}</x-ui.badge></div>
    </div>
    <div class="pm-property__body">
        <div class="pm-property__location"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ $villa->location ?: __('Lokasi belum diatur') }}</div>
        <a href="{{ route('villas.show', $villa) }}" class="pm-property__title">{{ $villa->name }}</a>
        <dl class="pm-property__meta">
            <div><dt>{{ __('Owner') }}</dt><dd>{{ $villa->owner?->name ?: __('Belum ada') }}</dd></div>
            <div><dt>{{ __('Management fee') }}</dt><dd>{{ \App\Support\Rupiah::percent($villa->fee_percentage) }} <span class="text-muted fs-xs">· {{ $villa->fee_basis->label() }}</span></dd></div>
            <div><dt>{{ __('Wilayah') }}</dt><dd>{{ $villa->region?->name ?: '—' }}</dd></div>
            <div><dt>{{ __('Staff') }}</dt><dd>{{ $villa->staff->pluck('name')->take(2)->join(', ') ?: __('Belum ada') }}@if($villa->staff->count() > 2) +{{ $villa->staff->count() - 2 }}@endif</dd></div>
        </dl>
        <div class="pm-property__channels">
            @forelse ($villa->channels as $channel)
                <span><span class="pm-platform-dot" style="background: {{ $channel->platform->color }}" aria-hidden="true"></span>{{ $channel->platform->name }}</span>
            @empty
                <span><i class="bi bi-calendar2" aria-hidden="true"></i>{{ __('Reservasi manual') }}</span>
            @endforelse
        </div>
        <div class="pm-property__footer">
            <a href="{{ route('villas.show', $villa) }}" class="btn btn-sm btn-light">{{ __('Lihat detail villa') }}<i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            @can('update', $villa)<a href="{{ route('villas.edit', $villa) }}" class="btn btn-sm btn-ghost" aria-label="{{ __('Edit :name', ['name' => $villa->name]) }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>@endcan
        </div>
    </div>
</article>
