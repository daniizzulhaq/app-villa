{{--
  Input nominal Rupiah. Saat diketik tampil "1.250.000"; yang dikirim ke server angka murni "1250000".
  inputmode="numeric" → keyboard angka di HP.
--}}
@props(['name', 'label' => null, 'value' => null, 'help' => null, 'required' => false, 'id' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id ??= 'f_'.str_replace('.', '_', $key);
    $hasError = $errors->has($key);
    $initial = old($key, $value);
    $initial = $initial === null || $initial === '' ? '' : (string) round((float) $initial);
@endphp
<div {{ $attributes->only('class')->class('mb-3') }} data-field x-data="moneyInput(@js($initial))">
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }}@if($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    @endif
    <div class="input-group">
        <span class="input-group-text">Rp</span>
        <input type="text" id="{{ $id }}" inputmode="numeric" autocomplete="off" placeholder="0"
               data-label="{{ $label }}" :value="display" @input="onInput($event)"
               @if($required) required aria-required="true" @endif
               @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}_error" @endif
               {{ $attributes->except('class')->class(['form-control num fw-semibold', 'is-invalid' => $hasError]) }}>
    </div>
    <input type="hidden" name="{{ $name }}" :value="raw">
    @if ($hasError)
        <div class="invalid-feedback d-block" id="{{ $id }}_error">{{ $errors->first($key) }}</div>
    @endif
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
