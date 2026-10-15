@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null, 'col' => null, 'multiple' => false])
@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name));
    $dotName = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $selected = old($dotName, $value);
    $selected = is_array($selected) ? array_map('strval', $selected) : (is_null($selected) ? null : (string) $selected);
    $hasError = $errors->has($dotName);
@endphp
<div class="{{ $col ?? 'mb-3' }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="req">*</span>@endif</label>
    @endif
    <select name="{{ $name }}" id="{{ $id }}" @required($required) @if ($multiple) multiple @endif
        {{ $attributes->except('id')->merge(['class' => 'form-select'.($hasError ? ' is-invalid' : '')]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $option)
            @php($text = is_array($option) ? $option[0] : $option)
            <option value="{{ $key }}" @selected(is_array($selected) ? in_array((string) $key, $selected, true) : (string) $key === $selected)>{{ $text }}</option>
        @endforeach
    </select>
    <div class="invalid-feedback">{{ $hasError ? $errors->first($dotName) : __('Vui lòng chọn :field.', ['field' => mb_strtolower($label ?? __('một giá trị'))]) }}</div>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
