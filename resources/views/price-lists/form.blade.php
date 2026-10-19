@extends('layouts.app')
@section('title', $list->exists ? $list->name : __('Tạo bảng giá'))
@section('content')
    <x-page-header :title="$list->exists ? __('Bảng giá: :name', ['name' => $list->name]) : __('Tạo bảng giá')"
                   :breadcrumbs="['Bảng giá' => route('price-lists.index'), ($list->exists ? 'Chỉnh sửa' : 'Thêm mới') => null]" />
    <form method="POST" action="{{ $list->exists ? route('price-lists.update', $list) : route('price-lists.store') }}" class="needs-validation" novalidate>
        @csrf @if ($list->exists) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4"><div class="card-body"><div class="row">
                    <x-input name="name" :label="__('Tên bảng giá')" :value="$list->name" required maxlength="150" placeholder="VD: Đại lý cấp 1" col="col-md-6 mb-3" />
                    <x-input name="description" :label="__('Mô tả')" :value="$list->description" maxlength="500" col="col-md-6 mb-3" />
                    <div class="col-12"><div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $list->is_active))>
                        <label class="form-check-label" for="is_active">{{ __('Đang dùng (tắt: khách hàng gắn bảng giá này dùng giá chuẩn)') }}</label>
                    </div></div>
                </div></div></div>

                <div class="card">
                    <div class="card-header"><span>{{ __('Giá theo sản phẩm') }}</span>
                        <button type="button" class="btn btn-soft-primary btn-sm" data-add-price><i class="bi bi-plus"></i> {{ __('Thêm dòng') }}</button></div>
                    <div class="table-responsive"><table class="table mb-0 align-middle" data-price-rows>
                        <thead><tr><th style="min-width:260px">{{ __('Sản phẩm') }}</th><th class="text-end" style="width:150px">{{ __('Giá chuẩn') }}</th><th style="width:170px">{{ __('Giá trong bảng giá') }}</th><th style="width:44px"></th></tr></thead>
                        <tbody>
                            @foreach (old('items', $rows) as $i => $row)
                                @include('price-lists._row', ['index' => $i, 'row' => $row])
                            @endforeach
                        </tbody>
                    </table></div>
                    @error('items.*.product_id')<div class="text-danger small px-3 pb-3">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="col-lg-4">
                <div class="d-flex gap-2 mb-4">
                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2 me-1"></i>{{ __('Lưu') }}</button>
                    <a href="{{ route('price-lists.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
                </div>
                @if ($list->exists)
                    <div class="card"><div class="card-header">{{ __('Khách hàng áp dụng') }}</div><div class="card-body small">
                        @forelse ($customers as $c)
                            <div>@can('view', $c)<a href="{{ route('customers.show', $c) }}">{{ $c->name }}</a>@else {{ $c->name }} @endcan <span class="text-muted">{{ $c->code }}</span></div>
                        @empty
                            <span class="text-muted">{{ __('Chưa có khách hàng nào. Gắn bảng giá ở trang sửa khách hàng.') }}</span>
                        @endforelse
                    </div></div>
                @endif
            </div>
        </div>
    </form>
    <template id="priceRowTemplate">@include('price-lists._row', ['index' => '__INDEX__', 'row' => []])</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.querySelector('[data-price-rows] tbody');
    const tpl = document.getElementById('priceRowTemplate');
    let index = tbody.children.length;
    const fmt = new Intl.NumberFormat('vi-VN');
    const bind = (tr) => {
        const sel = tr.querySelector('select');
        const base = tr.querySelector('[data-base]');
        const show = () => { const p = sel.selectedOptions[0]?.dataset.price; base.textContent = p ? fmt.format(p) + ' ₫' : '—'; };
        sel.addEventListener('change', () => {
            show();
            const price = tr.querySelector('[data-price-input]');
            if (!price.value && sel.selectedOptions[0]?.dataset.price) price.value = sel.selectedOptions[0].dataset.price;
        });
        tr.querySelector('[data-remove]').addEventListener('click', () => tr.remove());
        show();
    };
    [...tbody.children].forEach(bind);
    document.querySelector('[data-add-price]').addEventListener('click', () => {
        tbody.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__INDEX__', index++));
        bind(tbody.lastElementChild);
    });
});
</script>
@endpush
