<tr>
    <td>
        <select name="items[{{ $index }}][product_id]" class="form-select" required aria-label="{{ __('Sản phẩm') }}">
            <option value="">{{ __('— Chọn sản phẩm —') }}</option>
            @foreach ($products as $p)
                <option value="{{ $p->id }}" @selected(($row['product_id'] ?? null) == $p->id)>{{ $p->sku }} — {{ $p->display_name }} ({{ $p->unit }})</option>
            @endforeach
        </select>
    </td>
    <td class="text-end small text-muted" data-stock>—</td>
    @if ($type === 'adjustment')
        <td><input type="number" name="items[{{ $index }}][counted_quantity]" class="form-control" data-counted value="{{ $row['counted_quantity'] ?? '' }}" min="0" step="0.001" required aria-label="{{ __('Số đếm thực tế') }}"></td>
        <td class="text-end fw-semibold" data-diff>—</td>
    @else
        <td><input type="number" name="items[{{ $index }}][quantity]" class="form-control" value="{{ $row['quantity'] ?? '' }}" min="0.001" step="0.001" required aria-label="{{ __('Số lượng') }}"></td>
    @endif
    @if ($type === 'receipt' && $canCost)
        <td><input type="number" name="items[{{ $index }}][unit_cost]" class="form-control" value="{{ $row['unit_cost'] ?? '' }}" min="0" step="1" aria-label="{{ __('Đơn giá nhập') }}" placeholder="{{ __('Để trống: giá vốn hiện tại') }}"></td>
    @endif
    <td><button type="button" class="btn btn-light btn-icon btn-sm text-danger" data-remove title="{{ __('Xóa dòng') }}"><i class="bi bi-x-lg"></i></button></td>
</tr>
