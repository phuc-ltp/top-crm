<tr>
    <td>
        <select name="items[{{ $index }}][product_id]" class="form-select" data-field="product_id" aria-label="{{ __('Sản phẩm') }}">
            <option value="">{{ __('— Tự nhập —') }}</option>
            @foreach ($products as $p)
                <option value="{{ $p->id }}" data-price="{{ (int) $p->price }}" data-name="{{ $p->display_name }}" @selected(($item['product_id'] ?? null) == $p->id)>{{ $p->sku }} — {{ $p->display_name }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="text" name="items[{{ $index }}][description]" class="form-control" data-field="description" value="{{ $item['description'] ?? '' }}" maxlength="255" placeholder="{{ __('Mô tả') }}" aria-label="{{ __('Mô tả') }}"></td>
    <td><input type="number" name="items[{{ $index }}][quantity]" class="form-control" data-field="quantity" value="{{ isset($item['quantity']) ? (float) $item['quantity'] : 1 }}" min="0.01" step="0.01" required aria-label="{{ __('Số lượng') }}"></td>
    <td><input type="number" name="items[{{ $index }}][unit_price]" class="form-control" data-field="unit_price" value="{{ isset($item['unit_price']) ? (float) $item['unit_price'] : '' }}" min="0" step="1" aria-label="{{ __('Đơn giá') }}"></td>
    <td><input type="number" name="items[{{ $index }}][discount_percent]" class="form-control" data-field="discount_percent" value="{{ isset($item['discount_percent']) ? (float) $item['discount_percent'] : 0 }}" min="0" max="100" step="0.5" aria-label="{{ __('% giảm') }}"></td>
    <td class="text-end fw-semibold" data-line-total>0</td>
    <td><button type="button" class="btn btn-light btn-icon btn-sm text-danger" data-remove-row title="{{ __('Xóa dòng') }}"><i class="bi bi-x-lg"></i></button></td>
</tr>
