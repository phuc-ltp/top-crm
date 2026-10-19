@extends('layouts.app')
@php([$typeLabel] = App\Models\StockDocument::TYPES[$type])
@section('title', __($typeLabel))
@section('content')
    <x-page-header :title="__($typeLabel)" :breadcrumbs="['Phiếu kho' => route('stock-documents.index'), 'Lập phiếu' => null]"
                   :subtitle="__('Phiếu được ghi sổ ngay khi lưu và không sửa được sau đó — hãy kiểm tra kỹ trước khi lưu.')" />
    <form method="POST" action="{{ route('stock-documents.store') }}" class="needs-validation" novalidate data-stock-form>
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="card mb-4"><div class="card-body"><div class="row">
            <x-select name="warehouse_id" :label="$type === 'transfer' ? __('Kho xuất') : __('Kho')" :options="$warehouses->pluck('name', 'id')->all()"
                      :value="old('warehouse_id', $warehouses->firstWhere('is_default', true)?->id ?? $warehouses->first()?->id)" required col="col-md-4 mb-3" data-warehouse />
            @if ($type === 'transfer')
                <x-select name="to_warehouse_id" :label="__('Kho nhận')" :options="$warehouses->pluck('name', 'id')->all()" :value="old('to_warehouse_id')" :placeholder="__('— Chọn kho nhận —')" required col="col-md-4 mb-3" />
            @endif
            @if ($type === 'receipt')
                <x-select name="supplier_id" :label="__('Nhà cung cấp')" :options="$suppliers->all()" :value="old('supplier_id')" :placeholder="__('— Không có —')" col="col-md-4 mb-3" />
            @endif
            <x-input name="document_date" type="date" :label="__('Ngày chứng từ')" :value="old('document_date', today()->toDateString())" required :max="today()->toDateString()" col="col-md-4 mb-3" />
            <x-input name="reference" :label="__('Số tham chiếu')" :value="old('reference')" maxlength="100" :placeholder="__('VD: số hóa đơn của nhà cung cấp')" col="col-md-6 mb-3" />
            <x-input name="notes" :label="__('Ghi chú')" :value="old('notes')" maxlength="2000" col="col-md-6 mb-3" />
        </div></div></div>

        <div class="card mb-4">
            <div class="card-header"><span>{{ __('Hàng hóa') }}</span>
                <button type="button" class="btn btn-soft-primary btn-sm" data-add-line><i class="bi bi-plus"></i> {{ __('Thêm dòng') }}</button></div>
            <div class="table-responsive"><table class="table mb-0 align-middle" data-lines>
                <thead><tr>
                    <th style="min-width:260px">{{ __('Sản phẩm') }}</th>
                    <th class="text-end" style="width:150px">{{ __('Tồn hiện tại') }}</th>
                    @if ($type === 'adjustment')
                        <th style="width:160px">{{ __('Số đếm thực tế') }}</th><th class="text-end" style="width:130px">{{ __('Chênh lệch') }}</th>
                    @else
                        <th style="width:150px">{{ __('Số lượng') }}</th>
                    @endif
                    @if ($type === 'receipt' && $canCost)<th style="width:170px">{{ __('Đơn giá nhập') }}</th>@endif
                    <th style="width:44px"></th>
                </tr></thead>
                <tbody>
                    @foreach (old('items', [[]]) as $i => $row)
                        @include('stock-documents._line', ['index' => $i, 'row' => $row])
                    @endforeach
                </tbody>
            </table></div>
            @error('items')<div class="text-danger small px-3 pb-3">{{ $message }}</div>@enderror
            @error('items.*.product_id')<div class="text-danger small px-3 pb-3">{{ $message }}</div>@enderror
        </div>
        <div class="d-flex gap-2 justify-content-end">
            <a href="{{ route('stock-documents.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Ghi sổ') }}</button>
        </div>
    </form>
    <template id="stockLineTemplate">@include('stock-documents._line', ['index' => '__INDEX__', 'row' => []])</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const levels = @json($levels);
    const form = document.querySelector('[data-stock-form]');
    const tbody = form.querySelector('[data-lines] tbody');
    const tpl = document.getElementById('stockLineTemplate');
    const warehouse = form.querySelector('[name="warehouse_id"]');
    const fmt = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 });
    let index = tbody.children.length;
    const stockOf = (pid) => (levels[warehouse.value] || {})[pid] || { q: 0, r: 0 };
    const refresh = (tr) => {
        const pid = tr.querySelector('select').value;
        const s = stockOf(pid);
        tr.querySelector('[data-stock]').textContent = pid ? fmt.format(s.q) + (s.r ? ` (${t('giữ')} ${fmt.format(s.r)})` : '') : '—';
        const counted = tr.querySelector('[data-counted]');
        const diff = tr.querySelector('[data-diff]');
        if (counted && diff) {
            const d = counted.value === '' || !pid ? null : Number(counted.value) - s.q;
            diff.textContent = d === null ? '—' : (d > 0 ? '+' : '') + fmt.format(d);
            diff.className = 'text-end fw-semibold ' + (d > 0 ? 'text-success' : d < 0 ? 'text-danger' : '');
        }
    };
    const bind = (tr) => {
        tr.querySelector('select').addEventListener('change', () => refresh(tr));
        tr.querySelector('[data-counted]')?.addEventListener('input', () => refresh(tr));
        tr.querySelector('[data-remove]').addEventListener('click', () => { if (tbody.children.length > 1) tr.remove(); });
        refresh(tr);
    };
    [...tbody.children].forEach(bind);
    warehouse.addEventListener('change', () => [...tbody.children].forEach(refresh));
    form.querySelector('[data-add-line]').addEventListener('click', () => {
        tbody.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__INDEX__', index++));
        bind(tbody.lastElementChild);
    });
});
</script>
@endpush
