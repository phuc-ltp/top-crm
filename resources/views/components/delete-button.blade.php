@props(['action', 'message' => 'Bạn chắc chắn muốn xóa bản ghi này?', 'label' => null, 'class' => 'btn btn-light btn-icon btn-sm text-danger'])
<form method="POST" action="{{ $action }}" class="d-inline" data-confirm="{{ $message }}" data-confirm-button="Xóa">
    @csrf @method('DELETE')
    <button type="submit" class="{{ $class }}" title="Xóa" data-loading-text="">
        <i class="bi bi-trash"></i>@if ($label) {{ $label }}@endif
    </button>
</form>
