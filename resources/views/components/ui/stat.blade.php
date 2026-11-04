{{--
  Kartu angka. Label kecil abu-abu, angka besar tegas.
  tone: null | positive | negative  → warna angka (laba hijau / minus merah)
  hero: kartu utama (Net Profit) — latar navy agar paling menonjol
--}}
@props(['label', 'value', 'hint' => null, 'icon' => null, 'iconTone' => null, 'tone' => null, 'hero' => false, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['pm-card pm-stat text-decoration-none', 'pm-stat--hero' => $hero]) }}>
    <div class="pm-stat__top">
        <span class="pm-stat__label">{{ $label }}</span>
        @if ($icon)
            <span @class(['pm-stat__icon', 'pm-stat__icon--'.$iconTone => $iconTone]) aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
        @endif
    </div>
    <div @class(['pm-stat__value', 'text-positive' => $tone === 'positive', 'text-negative' => $tone === 'negative'])>{{ $value }}</div>
    @if ($hint)
        <div class="pm-stat__hint">{{ $hint }}</div>
    @endif
    {{ $slot }}
</{{ $tag }}>
