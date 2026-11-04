{{--
  Badge status konsisten di semua halaman.
  <x-ui.badge :status="$expense->status" />   → enum dengan label() & badge()
  <x-ui.badge variant="success">Aktif</x-ui.badge>
  Varian: warning (Menunggu), success (Valid), danger (Dikembalikan), info (Final), primary, neutral
--}}
@props(['status' => null, 'variant' => null, 'plain' => false])
@php
    $variant ??= $status?->badge() ?? 'neutral';
    $text = $slot->isEmpty() ? $status?->label() : $slot;
@endphp
<span {{ $attributes->class(['pm-badge', 'pm-badge--'.$variant, 'pm-badge--plain' => $plain]) }}>{{ $text }}</span>
