{{-- <x-form.select name="owner_id" label="Owner" :options="[id => label]" placeholder="Pilih owner" /> --}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'help' => null, 'required' => false, 'id' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id ??= 'f_'.str_replace('.', '_', $key);
    $hasError = $errors->has($key);
    $selected = (string) old($key, $value instanceof \BackedEnum ? $value->value : $value);
    $describedBy = trim(($help ? $id.'_help ' : '').($hasError ? $id.'_error' : ''));
@endphp
<div {{ $attributes->only('class')->class('mb-3') }} data-field>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }}@if($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    @endif
    <select name="{{ $name }}" id="{{ $id }}" data-label="{{ $label }}"
            @if($required) required aria-required="true" @endif
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except('class')->class(['form-select', 'is-invalid' => $hasError]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($selected === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @if ($hasError)
        <div class="invalid-feedback d-block" id="{{ $id }}_error">{{ $errors->first($key) }}</div>
    @endif
    @if ($help)
        <div class="form-text" id="{{ $id }}_help">{{ $help }}</div>
    @endif
</div>
