@props(['statuses'])
<nav class="pm-status-tabs" aria-label="{{ __('Filter status') }}">
    <a href="{{ request()->fullUrlWithQuery(['status' => null, 'page' => null]) }}" @class(['active' => ! request()->filled('status')]) @if(!request()->filled('status')) aria-current="page" @endif>{{ __('Semua') }}</a>
    @foreach ($statuses as $status)
        <a href="{{ request()->fullUrlWithQuery(['status' => $status->value, 'page' => null]) }}" @class(['active' => request('status') === $status->value]) @if(request('status') === $status->value) aria-current="page" @endif><span class="pm-status-tabs__dot pm-status-tabs__dot--{{ $status->badge() }}" aria-hidden="true"></span>{{ $status->label() }}</a>
    @endforeach
</nav>
