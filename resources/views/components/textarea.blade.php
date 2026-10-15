@props(['name', 'label' => null, 'value' => null, 'required' => false, 'rows' => 3, 'col' => null, 'help' => null])
@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
@endphp
<div class="{{ $col ?? 'mb-3' }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="req">*</span>@endif</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" @required($required)
        {{ $attributes->except('id')->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}>{{ old($name, $value) }}</textarea>
    <div class="invalid-feedback">{{ $hasError ? $errors->first($name) : __('Vui lòng nhập :field.', ['field' => mb_strtolower($label ?? __('nội dung'))]) }}</div>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
