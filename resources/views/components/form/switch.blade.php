{{-- Saklar on/off. Hidden input 0 memastikan nilai "mati" tetap terkirim. --}}
@props(['name', 'label', 'checked' => false, 'help' => null, 'id' => null])
@php
    $id ??= 'f_'.$name;
    $isOn = (bool) old($name, $checked);
@endphp
<div {{ $attributes->class('mb-3') }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <div class="form-check form-switch d-flex align-items-center gap-3 ps-0">
        <input class="form-check-input ms-0 flex-shrink-0" type="checkbox" role="switch" id="{{ $id }}" name="{{ $name }}" value="1" @checked($isOn)
               @if($help) aria-describedby="{{ $id }}_help" @endif>
        <label class="form-check-label" for="{{ $id }}">
            <span class="fw-semibold text-ink d-block">{{ $label }}</span>
            @if ($help)<span class="fs-sm text-muted" id="{{ $id }}_help">{{ $help }}</span>@endif
        </label>
    </div>
    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
