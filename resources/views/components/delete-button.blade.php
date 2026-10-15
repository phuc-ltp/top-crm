@props(['action', 'message' => null, 'label' => null, 'class' => 'btn btn-light btn-icon btn-sm text-danger'])
<form method="POST" action="{{ $action }}" class="d-inline" data-confirm="{{ $message ?? __('Bạn chắc chắn muốn xóa bản ghi này?') }}" data-confirm-button="{{ __('Xóa') }}">
    @csrf @method('DELETE')
    <button type="submit" class="{{ $class }}" title="{{ __('Xóa') }}" data-loading-text="">
        <i class="bi bi-trash"></i>@if ($label) {{ $label }}@endif
    </button>
</form>
