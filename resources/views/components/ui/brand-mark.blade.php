@props(['light' => false])
<span {{ $attributes->class(['pm-brand__mark', 'pm-brand__mark--light' => $light]) }} aria-hidden="true">
    <img src="{{ asset('images/propertyme-logo.png') }}" alt="" width="1711" height="919" decoding="async">
</span>
