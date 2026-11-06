<x-layouts.app :title="__('Reservasi')">
    <x-ui.page-header icon="bi-calendar2-week" :title="__('Reservasi & Kalender')" :subtitle="__('Daftar reservasi dari Airbnb, Booking.com, dan Direct Booking.')">
        <x-slot:actions>
            @can('create', \App\Models\Booking::class)
                <a href="{{ route('sync.index') }}" class="btn btn-light"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{ __('Status Sinkron') }}</a>
                <a href="{{ route('bookings.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah Reservasi') }}</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('bookings._tabs')

    <x-ui.card flush>
        <x-ui.filters :reset="route('bookings.index')" :advanced-keys="['platform', 'from', 'to']">
            @if(request()->filled('status'))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <div class="pm-filter-field pm-filter-field--search">
                <label for="q">{{ __('Cari reservasi') }}</label>
                <div class="pm-filter-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Cari nama tamu atau kode booking…') }}">
                </div>
            </div>
            <div class="pm-filter-field"><label for="fv">{{ __('Villa') }}</label>
            <select id="fv" name="villa" class="form-select">
                <option value="">{{ __('Semua villa') }}</option>
                @foreach ($villas as $id => $name)<option value="{{ $id }}" @selected((string) ($filters['villa'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach
            </select></div>
            <x-slot:advanced>
            <div class="pm-filter-field"><label for="fp">{{ __('Sumber') }}</label>
            <select id="fp" name="platform" class="form-select">
                <option value="">{{ __('Semua sumber') }}</option>
                @foreach ($platforms as $p)<option value="{{ $p->id }}" @selected((string) ($filters['platform'] ?? '') === (string) $p->id)>{{ $p->name }}</option>@endforeach
            </select></div>
            <div class="pm-filter-field"><label for="filter-from">{{ __('Dari tanggal') }}</label><input type="date" id="filter-from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control"></div>
            <div class="pm-filter-field"><label for="filter-to">{{ __('Sampai tanggal') }}</label><input type="date" id="filter-to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control"></div>
            </x-slot:advanced>
        </x-ui.filters>
        <x-ui.status-tabs :statuses="\App\Enums\BookingStatus::cases()" />

        @if ($bookings->isEmpty())
            <x-ui.empty-state art="search" :title="array_filter($filters) ? __('Tidak ada hasil yang cocok') : __('Belum ada reservasi')" :text="array_filter($filters) ? __('Coba kata kunci lain atau reset filter.') : __('Reservasi dari iCal muncul otomatis tiap 30 menit. Direct booking bisa dicatat manual.')">
                @if(array_filter($filters))<a href="{{ route('bookings.index') }}" class="btn btn-light">{{ __('Reset filter') }}</a>@endif
                @can('create', \App\Models\Booking::class)
                    <a href="{{ route('bookings.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah Reservasi') }}</a>
                @endcan
            </x-ui.empty-state>
        @else
            <div class="table-responsive">
                <table class="table pm-table pm-table--stack">
                    <thead><tr><th>{{ __('Tamu') }}</th><th>{{ __('Villa') }}</th><th>{{ __('Tanggal') }}</th><th>{{ __('Sumber') }}</th><th class="num">{{ __('Pendapatan') }}</th><th>{{ __('Status') }}</th><th><span class="visually-hidden">{{ __('Aksi') }}</span></th></tr></thead>
                    <tbody>
                    @foreach ($bookings as $b)
                        <tr>
                            <td class="stack-full">
                                <div class="pm-person-cell"><span class="pm-guest-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($b->guest_name ?: '?', 0, 1)) }}</span><div><a href="{{ route('bookings.show', $b) }}" class="cell-title text-decoration-none">{{ $b->guest_name ?: __('Nama belum diisi') }}</a>
                                <div class="cell-sub">{{ $b->booking_ref ?: '—' }}@if($b->guest_count) {{ __('· :guest_count tamu', ['guest_count' => $b->guest_count]) }} @endif</div></div></div>
                            </td>
                            <td data-label="{{ __('Villa') }}">{{ $b->villa->name }}</td>
                            <td data-label="{{ __('Tanggal') }}" class="text-nowrap">
                                {{ $b->check_in->translatedFormat('d M') }} – {{ $b->check_out->translatedFormat('d M Y') }}
                                <div class="cell-sub">{{ __(':nights malam', ['nights' => $b->nights()]) }}</div>
                            </td>
                            <td data-label="{{ __('Sumber') }}"><span class="d-inline-flex align-items-center gap-1"><span class="pm-platform-dot" style="background: {{ $b->platform->color }}" aria-hidden="true"></span>{{ $b->platform->name }}</span></td>
                            <td data-label="{{ __('Pendapatan') }}" class="num">
                                @if ($b->incomes_sum_gross_amount)
                                    @rupiah($b->incomes_sum_gross_amount)
                                @else
                                    <span class="text-muted fs-sm">{{ __('Belum dicatat') }}</span>
                                @endif
                            </td>
                            <td data-label="{{ __('Status') }}"><x-ui.badge :status="$b->status" /></td>
                            <td class="text-end stack-actions">
                                @can('update', $b)
                                    <a href="{{ route('bookings.edit', $b) }}" class="btn btn-sm {{ $b->status === \App\Enums\BookingStatus::NeedsCompletion ? 'btn-primary' : 'btn-light' }}">
                                        {{ $b->status === \App\Enums\BookingStatus::NeedsCompletion ? __('Lengkapi') : __('Edit') }}
                                    </a>
                                @else
                                    <a href="{{ route('bookings.show', $b) }}" class="btn btn-sm btn-light">{{ __('Detail') }}</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-ui.results-footer :items="$bookings" />
    </x-ui.card>
</x-layouts.app>
