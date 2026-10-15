@props(['name', 'icon', 'placeholder', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null])
@php($hasError = $errors->has($name))
<div class="ta-field">
    <label for="{{ $name }}" class="visually-hidden">{{ $placeholder }}</label>
    <i class="bi {{ $icon }} lead-icon" aria-hidden="true"></i>
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}" placeholder="{{ $placeholder }}" @required($required)
           value="{{ $type === 'password' ? '' : old($name, $value) }}"
           {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}>
    @if ($type === 'password')
        <button type="button" class="toggle-pw" data-toggle-password="{{ $name }}" aria-label="Hiện/ẩn mật khẩu"><i class="bi bi-eye-slash"></i></button>
    @endif
    <div class="invalid-feedback">{{ $hasError ? $errors->first($name) : 'Vui lòng nhập '.mb_strtolower($placeholder).'.' }}</div>
</div>
@if ($help)<div class="ta-help">{{ $help }}</div>@endif
