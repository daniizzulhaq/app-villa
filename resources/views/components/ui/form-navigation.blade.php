@props(['steps'])
<nav class="pm-form-navigation" aria-label="{{ __('Bagian formulir') }}">
    @foreach ($steps as $target => $label)
        <a href="#{{ $target }}"><span>{{ $loop->iteration }}</span>{{ $label }}<i class="bi bi-arrow-down-short" aria-hidden="true"></i></a>
    @endforeach
    <small>{{ __('Isian bertanda * wajib diisi.') }}</small>
</nav>
