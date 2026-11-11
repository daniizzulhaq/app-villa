@php
    $icalFeeds = $villa->channels->filter(fn ($channel) => filled($channel->ical_url));
    $hasActiveIcal = $icalFeeds->contains(fn ($channel) => $channel->is_active);
@endphp
@if ($icalFeeds->isNotEmpty())
    <div class="mt-3">
        @if ($hasActiveIcal)
            <p class="fs-sm text-warning-emphasis mb-2">{{ __('iCal is still enabled. Disable it before importing bookings from Hostex.') }}</p>
            @can('update', $villa)
                <form method="POST" action="{{ route('villas.disable-ical', $villa) }}"
                      data-confirm="{{ __('Disable iCal for this villa?') }}"
                      data-confirm-text="{{ __('iCal imports will stop. Existing bookings, notes and income records will be kept.') }}"
                      data-confirm-button="{{ __('Disable iCal') }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-sm btn-light w-100" data-loading-text="{{ __('Menyimpan…') }}">
                        <i class="bi bi-pause-circle" aria-hidden="true"></i> {{ __('Disable iCal') }}
                    </button>
                </form>
            @endcan
        @else
            <p class="fs-sm text-muted mb-0">{{ __('iCal is disabled. Bookings are imported through Hostex.') }}</p>
        @endif
    </div>
@endif
