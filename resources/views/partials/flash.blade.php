@php
    $flash = [];
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        if (session()->has($type)) {
            $flash[] = [$type, session($type)];
        }
    }
    if (isset($errors) && $errors->any()) {
        $flash[] = ['error', $errors->count() > 1 ? __('Dữ liệu chưa hợp lệ: :first (và :count lỗi khác)', ['first' => $errors->first(), 'count' => $errors->count() - 1]) : $errors->first()];
    }
@endphp
@if ($flash)
    <script type="application/json" id="flashData">@json($flash)</script>
@endif
