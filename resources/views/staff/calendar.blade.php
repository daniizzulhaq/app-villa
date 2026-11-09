<x-layouts.app :title="__('Kalender')">
    <x-ui.page-header :title="__('Halo, :name', ['name' => strtok(auth()->user()->name, ' ')])" :subtitle="today()->translatedFormat('l, d F Y')" />

    @if ($villas->isEmpty())
        <x-ui.card>
            <x-ui.empty-state art="villa" :title="__('Belum ada villa ditugaskan')" :text="__('Minta Manager menambahkan Anda ke villa yang Anda tangani.')" />
        </x-ui.card>
    @else
        {{-- Status villa saat ini: terisi / kosong --}}
        <div class="d-flex gap-2 overflow-auto pb-2 mb-2" aria-label="{{ __('Status villa hari ini') }}">
            @foreach ($villas as $v)
                @php $occ = $occupiedNow->contains($v->id); @endphp
                <a href="{{ route('staff.calendar', ['villa' => $v->id]) }}"
                   class="pm-card px-3 py-2 text-decoration-none flex-shrink-0 {{ $villaId === $v->id ? 'border-primary' : '' }}" style="min-width: 160px">
                    <div class="fw-semibold text-ink text-truncate">{{ $v->name }}</div>
                    <x-ui.badge :variant="$occ ? 'primary' : 'success'">{{ $occ ? __('Ada tamu') : __('Kosong') }}</x-ui.badge>
                </a>
            @endforeach
            @if ($villaId)
                <a href="{{ route('staff.calendar') }}" class="btn btn-light align-self-center flex-shrink-0">{{ __('Semua villa') }}</a>
            @endif
        </div>

        {{-- Panel Hari ini & Besok --}}
        <div class="row g-3 mb-3">
            @foreach ($panels as $key => $p)
                <div class="col-md-6">
                    <x-ui.card :title="$p['label']" :subtitle="$p['date']->translatedFormat('l, d M')">
                        <x-slot:actions>
                            <x-ui.badge variant="success" plain>{{ __(':count check-in', ['count' => $p['in']->count()]) }}</x-ui.badge>
                            <x-ui.badge variant="warning" plain>{{ __(':count check-out', ['count' => $p['out']->count()]) }}</x-ui.badge>
                        </x-slot:actions>
                        @if ($p['in']->isEmpty() && $p['out']->isEmpty())
                            <p class="text-muted mb-0 fs-sm"><i class="bi bi-cup-hot" aria-hidden="true"></i> {{ __('Tidak ada check-in atau check-out.') }}</p>
                        @else
                            <ul class="pm-agenda">
                                @foreach ($p['in'] as $b)
                                    <li>
                                        <span class="pm-agenda__icon pm-agenda__icon--in" aria-hidden="true"><i class="bi bi-box-arrow-in-right"></i></span>
                                        <div class="min-w-0">
                                            <div class="pm-agenda__title">{{ __('Check-in · :name', ['name' => $b->villa->name]) }}</div>
                                            <div class="pm-agenda__meta">
                                                {{ $b->guest_name ?: __('Nama tamu belum ada') }}
                                                @if ($b->guest_count) {{ __('· :guest_count tamu', ['guest_count' => $b->guest_count]) }} @endif @if ($b->phone_last4) {{ __('· HP ••:digits', ['digits' => $b->phone_last4]) }} @endif
                                                @if ($b->arrival_time) · <strong class="text-ink">{{ __('jam :arrival_time', ['arrival_time' => substr($b->arrival_time, 0, 5)]) }}</strong> @endif
                                                {{ __('· :nights malam', ['nights' => $b->nights()]) }}
                                            </div>
                                            @if ($b->notes)<div class="fs-sm mt-1" style="white-space:pre-line"><i class="bi bi-chat-left-text text-muted" aria-hidden="true"></i> {{ $b->notes }}</div>@endif
                                        </div>
                                    </li>
                                @endforeach
                                @foreach ($p['out'] as $b)
                                    <li>
                                        <span class="pm-agenda__icon pm-agenda__icon--out" aria-hidden="true"><i class="bi bi-box-arrow-right"></i></span>
                                        <div class="min-w-0">
                                            <div class="pm-agenda__title">{{ __('Check-out · :name', ['name' => $b->villa->name]) }}</div>
                                            <div class="pm-agenda__meta">{{ __(':tamu · siapkan cleaning & laundry', ['tamu' => $b->guest_name ?: __('Tamu')]) }}</div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-ui.card>
                </div>
            @endforeach
        </div>

        <x-ui.card :title="__('Kalender reservasi')">
            <x-booking-calendar :villas="$villas->pluck('name', 'id')" :platforms="$platforms" :selected="$villaId" :initial-date="$initialDate" />
        </x-ui.card>
    @endif
</x-layouts.app>
