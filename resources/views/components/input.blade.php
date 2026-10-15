@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'col' => null, 'prepend' => null, 'append' => null])
@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name));
    $dotName = str_replace(['[', ']'], ['.', ''], $name);
    $val = $type === 'password' ? null : old($dotName, $value);
    $hasError = $errors->has($dotName);
@endphp
<div class="{{ $col ?? 'mb-3' }}">
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="req">*</span>@endif</label>
    @endif
    @if ($prepend || $append)<div class="input-group has-validation">@endif
        @if ($prepend)<span class="input-group-text">{!! $prepend !!}</span>@endif
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $val }}" @required($required)
            {{ $attributes->except('id')->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}>
        @if ($append)<span class="input-group-text">{!! $append !!}</span>@endif
        <div class="invalid-feedback">{{ $hasError ? $errors->first($dotName) : 'Vui lòng nhập '.mb_strtolower($label ?? 'giá trị hợp lệ').'.' }}</div>
    @if ($prepend || $append)</div>@endif
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
