<x-layouts.app :title="__('Reservasi')">
    <x-ui.page-header :title="__('Reservasi & Kalender')" :subtitle="__('Daftar reservasi dari Airbnb, Booking.com, dan Direct Booking.')">
        <x-slot:actions>
            @can('create', \App\Models\Booking::class)
                <a href="{{ route('sync.index') }}" class="btn btn-light"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> {{ __('Status Sinkron') }}</a>
                <a href="{{ route('bookings.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Tambah Reservasi') }}</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @include('bookings._tabs')

    <x-ui.card flush>
        <form method="GET" class="pm-toolbar" role="search" data-no-lock>
            <div class="pm-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label for="q" class="visually-hidden">{{ __('Cari reservasi') }}</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Cari nama tamu atau kode booking…') }}">
            </div>
            <label class="visually-hidden" for="fv">{{ __('Villa') }}</label>
            <select id="fv" name="villa" class="form-select" onchange="this.form.submit()">
                <option value="">{{ __('Semua villa') }}</option>
                @foreach ($villas as $id => $name)<option value="{{ $id }}" @selected((string) ($filters['villa'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
            <label class="visually-hidden" for="fp">{{ __('Sumber') }}</label>
            <select id="fp" name="platform" class="form-select" onchange="this.form.submit()">
                <option value="">{{ __('Semua sumber') }}</option>
                @foreach ($platforms as $p)<option value="{{ $p->id }}" @selected((string) ($filters['platform'] ?? '') === (string) $p->id)>{{ $p->name }}</option>@endforeach
            </select>
            <label class="visually-hidden" for="fs">{{ __('Status') }}</label>
            <select id="fs" name="status" class="form-select" onchange="this.form.submit()">
                <option value="">{{ __('Semua status') }}</option>
                @foreach (\App\Enums\BookingStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach
            </select>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control" style="flex:0 1 160px" aria-label="{{ __('Dari tanggal') }}">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control" style="flex:0 1 160px" aria-label="{{ __('Sampai tanggal') }}">
            <button type="submit" class="btn btn-light"><i class="bi bi-funnel" aria-hidden="true"></i> {{ __('Terapkan') }}</button>
            @if (array_filter($filters))<a href="{{ route('bookings.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>@endif
        </form>

        @if ($bookings->isEmpty())
            <x-ui.empty-state art="search" :title="__('Belum ada reservasi')" :text="__('Reservasi dari iCal muncul otomatis tiap 30 menit. Direct booking bisa dicatat manual.')">
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
                                <a href="{{ route('bookings.show', $b) }}" class="cell-title text-decoration-none">{{ $b->guest_name ?: __('Nama belum diisi') }}</a>
                                <div class="cell-sub">{{ $b->booking_ref ?: '—' }}@if($b->guest_count) {{ __('· :guest_count tamu', ['guest_count' => $b->guest_count]) }} @endif</div>
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
            @if ($bookings->hasPages())
                <div class="p-3 border-top">{{ $bookings->onEachSide(1)->links() }}</div>
            @endif
        @endif
    </x-ui.card>
</x-layouts.app>
