<tr>
    <td>
        <select name="items[{{ $index }}][product_id]" class="form-select" required aria-label="{{ __('Sản phẩm') }}">
            <option value="">{{ __('— Chọn sản phẩm —') }}</option>
            @foreach ($products as $p)
                <option value="{{ $p->id }}" data-price="{{ (int) $p->price }}" @selected(($row['product_id'] ?? null) == $p->id)>{{ $p->sku }} — {{ $p->display_name }}</option>
            @endforeach
        </select>
    </td>
    <td class="text-end small text-muted" data-base>—</td>
    <td><input type="number" name="items[{{ $index }}][price]" class="form-control" data-price-input value="{{ isset($row['price']) ? (float) $row['price'] : '' }}" min="0" step="1" required aria-label="{{ __('Giá trong bảng giá') }}"></td>
    <td><button type="button" class="btn btn-light btn-icon btn-sm text-danger" data-remove title="{{ __('Xóa dòng') }}"><i class="bi bi-x-lg"></i></button></td>
</tr>
