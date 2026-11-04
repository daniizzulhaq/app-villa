@props(['name', 'label' => null, 'value' => null, 'help' => null, 'required' => false, 'rows' => 3, 'id' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id ??= 'f_'.str_replace('.', '_', $key);
    $hasError = $errors->has($key);
    $describedBy = trim(($help ? $id.'_help ' : '').($hasError ? $id.'_error' : ''));
@endphp
<div {{ $attributes->only('class')->class('mb-3') }} data-field>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }}@if($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" data-label="{{ $label }}"
              @if($required) required aria-required="true" @endif
              @if($hasError) aria-invalid="true" @endif
              @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
              {{ $attributes->except('class')->class(['form-control', 'is-invalid' => $hasError]) }}>{{ old($key, $value) }}</textarea>
    @if ($hasError)
        <div class="invalid-feedback d-block" id="{{ $id }}_error">{{ $errors->first($key) }}</div>
    @endif
    @if ($help)
        <div class="form-text" id="{{ $id }}_help">{{ $help }}</div>
    @endif
</div>
