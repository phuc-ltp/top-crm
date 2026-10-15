@php
    $flash = [];
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        if (session()->has($type)) {
            $flash[] = [$type, session($type)];
        }
    }
    if (isset($errors) && $errors->any()) {
        $flash[] = ['error', $errors->count() > 1 ? 'Dữ liệu chưa hợp lệ: '.$errors->first().' (và '.($errors->count() - 1).' lỗi khác)' : $errors->first()];
    }
@endphp
@if ($flash)
    <script type="application/json" id="flashData">@json($flash)</script>
@endif
