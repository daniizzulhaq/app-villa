{{--
  <x-form.input name="name" label="Nama villa" required help="..." prefix="Rp" suffix="%" />
  Error server ditampilkan tepat di bawah field; aria-describedby terhubung ke bantuan & error.
--}}
@props([
    'name', 'label' => null, 'type' => 'text', 'value' => null, 'help' => null,
    'required' => false, 'prefix' => null, 'suffix' => null, 'id' => null,
])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id ??= 'f_'.str_replace('.', '_', $key);
    $hasError = $errors->has($key);
    $describedBy = trim(($help ? $id.'_help ' : '').($hasError ? $id.'_error' : ''));
    $val = $type === 'password' ? null : old($key, $value);
@endphp
<div {{ $attributes->only('class')->class('mb-3') }} data-field>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }}@if($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    @endif
    @if ($prefix || $suffix)<div class="input-group">@endif
        @if ($prefix)<span class="input-group-text">{{ $prefix }}</span>@endif
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $val }}"
               data-label="{{ $label }}"
               @if($required) required aria-required="true" @endif
               @if($hasError) aria-invalid="true" @endif
               @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
               {{ $attributes->except('class')->class(['form-control', 'is-invalid' => $hasError]) }}>
        @if ($suffix)<span class="input-group-text">{{ $suffix }}</span>@endif
    @if ($prefix || $suffix)</div>@endif
    @if ($hasError)
        <div class="invalid-feedback d-block" id="{{ $id }}_error">{{ $errors->first($key) }}</div>
    @endif
    @if ($help)
        <div class="form-text" id="{{ $id }}_help">{{ $help }}</div>
    @endif
</div>
