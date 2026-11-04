<nav class="pm-nav">
    @foreach ($groups as $group)
        @if ($group['group'])
            <div class="pm-nav__group label-caps">{{ $group['group'] }}</div>
        @endif
        @foreach ($group['items'] as $item)
            @if ($item['available'])
                <a href="{{ $item['url'] }}" @class(['active' => $item['active']]) @if($item['active']) aria-current="page" @endif>
                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $item['label'] }}</span>
                    @if ($item['count'])<span class="pm-nav__count" aria-label="{{ $item['count'] }} perlu tindakan">{{ $item['count'] > 99 ? '99+' : $item['count'] }}</span>@endif
                </a>
            @else
                <span class="pm-nav__disabled" aria-disabled="true">
                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $item['label'] }}</span>
                    <span class="pm-nav__soon">{{ __('Segera') }}</span>
                </span>
            @endif
        @endforeach
    @endforeach
</nav>
