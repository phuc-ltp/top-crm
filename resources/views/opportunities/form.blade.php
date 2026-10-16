@extends('layouts.app')
@section('title', $opportunity->exists ? __('Sửa cơ hội') : __('Tạo cơ hội'))
@section('content')
    <x-page-header :title="$opportunity->exists ? __('Sửa: :name', ['name' => $opportunity->title]) : __('Tạo cơ hội bán hàng')" :breadcrumbs="['Pipeline' => route('opportunities.index'), ($opportunity->exists ? 'Chỉnh sửa' : 'Tạo mới') => null]" />
    @php
        $oldItems = old('items', $opportunity->exists ? $opportunity->items->map(fn ($i) => ['product_id' => $i->product_id, 'name' => $i->name, 'quantity' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price, 'discount_percent' => (float) $i->discount_percent])->all() : []);
        $selectedStage = (int) old('pipeline_stage_id', $opportunity->pipeline_stage_id);
        $selectedPipeline = (int) ($pipelines->first(fn ($p) => $p->stages->contains('id', $selectedStage))?->id ?? $opportunity->pipeline_id ?? $pipelines->first()?->id);
    @endphp
    <form method="POST" action="{{ $opportunity->exists ? route('opportunities.update', $opportunity) : route('opportunities.store') }}" class="needs-validation" novalidate>
        @csrf @if ($opportunity->exists) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4"><div class="card-header">{{ __('Thông tin cơ hội') }}</div><div class="card-body"><div class="row">
                    <x-input name="title" :label="__('Tên cơ hội')" :value="$opportunity->title" required maxlength="255" col="col-12 mb-3" />
                    <x-select name="customer_id" :label="__('labels.customer')" :options="$customers" :value="$opportunity->customer_id" required :placeholder="__('— Chọn khách hàng —')" col="col-md-6 mb-3" />
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="contact_id">{{ __('Người liên hệ') }}</label>
                        <select name="contact_id" id="contact_id" class="form-select @error('contact_id') is-invalid @enderror">
                            <option value="">{{ __('— Không chọn —') }}</option>
                            @foreach ($contacts as $ct)
                                <option value="{{ $ct->id }}" data-customer="{{ $ct->customer_id }}" @selected(old('contact_id', $opportunity->contact_id) == $ct->id)>{{ $ct->full_name }}</option>
                            @endforeach
                        </select>
                        @error('contact_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <x-input name="expected_close_date" type="date" :label="__('Ngày dự kiến chốt')" :value="optional($opportunity->expected_close_date)->format('Y-m-d')" col="col-md-6 mb-3" />
                    <x-input name="follow_up_at" type="datetime-local" :label="__('Hẹn theo dõi tiếp (không bắt buộc)')" col="col-md-6 mb-3" :help="__('Tạo việc theo dõi trong mục Hoạt động.')" />
                    <x-textarea name="notes" :label="__('Ghi chú')" :value="$opportunity->notes" maxlength="5000" col="col-12 mb-0" />
                </div></div></div>

                {{-- Sản phẩm / dịch vụ --}}
                <div class="card">
                    <div class="card-header"><span>{{ __('Sản phẩm / dịch vụ dự kiến') }}</span>
                        <button type="button" class="btn btn-soft-primary btn-sm" id="addItem"><i class="bi bi-plus-lg me-1"></i>{{ __('Thêm dòng') }}</button></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="itemsTable">
                            <thead><tr><th style="min-width:220px">{{ __('Sản phẩm / dịch vụ') }}</th><th style="width:90px">{{ __('SL') }}</th><th style="width:150px">{{ __('Đơn giá') }}</th><th style="width:90px">{{ __('CK %') }}</th><th class="text-end" style="width:140px">{{ __('Thành tiền') }}</th><th style="width:40px"></th></tr></thead>
                            <tbody></tbody>
                            <tfoot><tr><td colspan="4" class="text-end fw-semibold">{{ __('Tổng') }}</td><td class="text-end fw-bold" id="itemsTotal">0</td><td></td></tr></tfoot>
                        </table>
                    </div>
                    <div class="card-body pt-2">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="value_from_items" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="value_from_items" value="1" id="value_from_items" @checked(old('value_from_items', $opportunity->exists ? $opportunity->items->isNotEmpty() : false))>
                            <label class="form-check-label" for="value_from_items">{{ __('Giá trị cơ hội = tổng các dòng sản phẩm') }}</label>
                        </div>
                        <x-input name="value" type="number" :label="__('Giá trị cơ hội (₫)')" :value="(int) $opportunity->value" min="0" step="1000" col="mb-0" :help="__('Nhập tay khi không dùng dòng sản phẩm.')" />
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-4"><div class="card-header">{{ __('Pipeline & giai đoạn') }}</div><div class="card-body">
                    <div class="mb-3 {{ $pipelines->count() > 1 ? '' : 'd-none' }}">
                        <label class="form-label" for="pipeline_select">{{ __('Pipeline') }}</label>
                        <select id="pipeline_select" class="form-select" aria-label="{{ __('Pipeline') }}">
                            @foreach ($pipelines as $p)<option value="{{ $p->id }}" @selected($selectedPipeline === $p->id)>{{ $p->label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pipeline_stage_id">{{ __('Giai đoạn') }} <span class="req">*</span></label>
                        <select name="pipeline_stage_id" id="pipeline_stage_id" class="form-select @error('pipeline_stage_id') is-invalid @enderror" required>
                            @foreach ($pipelines as $p)
                                @foreach ($p->stages as $s)
                                    <option value="{{ $s->id }}" data-pipeline="{{ $p->id }}" data-prob="{{ $s->probability }}" data-lost="{{ $s->is_lost ? 1 : 0 }}" @selected($selectedStage === $s->id)>{{ $s->label }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        @error('pipeline_stage_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <x-input name="probability" type="number" :label="__('Xác suất thành công (%)')" :value="$opportunity->probability" required min="0" max="100" />
                    <div id="lostReasonBox">
                        <x-select name="lost_reason_id" :label="__('Lý do thất bại')" :options="$lostReasons->all()" :value="$opportunity->lost_reason_id" :translate="false" :placeholder="__('— Chọn lý do —')" />
                        <x-input name="lost_reason" :label="__('Ghi chú lý do')" :value="$opportunity->lost_reason" maxlength="500" />
                    </div>
                    @can('opportunities.assign')
                        <x-select name="assigned_to" :label="__('Nhân viên phụ trách')" :options="$users" :value="$opportunity->assigned_to" :placeholder="__('— Chưa phân công —')" />
                    @endcan
                </div></div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2 me-1"></i>{{ __('Lưu cơ hội') }}</button>
                    <a href="{{ $opportunity->exists ? route('opportunities.show', $opportunity) : route('opportunities.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
                </div>
            </div>
        </div>
    </form>

    <template id="itemRow">
        <tr>
            <td>
                <select class="form-select form-select-sm mb-1" data-f="product_id" aria-label="{{ __('Sản phẩm') }}">
                    <option value="">{{ __('— Sản phẩm / dịch vụ khác —') }}</option>
                    @foreach ($products as $pr)<option value="{{ $pr->id }}" data-name="{{ $pr->name }}" data-price="{{ (float) $pr->price }}">{{ $pr->sku }} — {{ $pr->name }}</option>@endforeach
                </select>
                <input type="text" class="form-control form-control-sm" data-f="name" maxlength="255" placeholder="{{ __('Tên hiển thị') }}" aria-label="{{ __('Tên') }}">
            </td>
            <td><input type="number" class="form-control form-control-sm" data-f="quantity" min="0.01" step="0.01" value="1" aria-label="{{ __('Số lượng') }}"></td>
            <td><input type="number" class="form-control form-control-sm" data-f="unit_price" min="0" step="1000" value="0" aria-label="{{ __('Đơn giá') }}"></td>
            <td><input type="number" class="form-control form-control-sm" data-f="discount_percent" min="0" max="100" step="0.5" value="0" aria-label="{{ __('Chiết khấu') }}"></td>
            <td class="text-end small fw-semibold" data-line>0</td>
            <td><button type="button" class="btn btn-link text-danger p-0" data-remove aria-label="{{ __('Xóa dòng') }}"><i class="bi bi-x-lg"></i></button></td>
        </tr>
    </template>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const customer = document.getElementById('customer_id');
    const contact = document.getElementById('contact_id');
    const pipeline = document.getElementById('pipeline_select');
    const stage = document.getElementById('pipeline_stage_id');
    const fmt = new Intl.NumberFormat('vi-VN');

    const filterContacts = () => {
        Array.from(contact.options).forEach(o => { if (o.value) o.hidden = o.dataset.customer !== customer.value; });
        if (contact.selectedOptions[0]?.hidden) contact.value = '';
    };
    // Chỉ hiện giai đoạn của pipeline đã chọn
    const filterStages = () => {
        let first = null;
        Array.from(stage.options).forEach(o => { const show = o.dataset.pipeline === pipeline.value; o.hidden = !show; o.disabled = !show; if (show && !first) first = o; });
        if (stage.selectedOptions[0]?.disabled && first) { stage.value = first.value; syncStage(true); }
    };
    const syncStage = (setProb) => {
        const opt = stage.selectedOptions[0];
        if (!opt) return;
        if (setProb) document.getElementById('probability').value = opt.dataset.prob;
        document.getElementById('lostReasonBox').style.display = opt.dataset.lost === '1' ? '' : 'none';
    };

    // Dòng sản phẩm
    const tbody = document.querySelector('#itemsTable tbody');
    const tpl = document.getElementById('itemRow');
    const recalc = () => {
        let total = 0;
        Array.from(tbody.rows).forEach((tr, i) => {
            const v = (f) => Number(tr.querySelector(`[data-f="${f}"]`).value || 0);
            const line = Math.round(v('quantity') * v('unit_price') * (1 - Math.min(100, Math.max(0, v('discount_percent'))) / 100) * 100) / 100;
            tr.querySelector('[data-line]').textContent = fmt.format(line);
            tr.querySelectorAll('[data-f]').forEach(el => el.name = `items[${i}][${el.dataset.f}]`);
            total += line;
        });
        document.getElementById('itemsTotal').textContent = fmt.format(total) + ' ₫';
        if (document.getElementById('value_from_items').checked && tbody.rows.length) document.getElementById('value').value = Math.round(total);
    };
    const addRow = (data = {}) => {
        const tr = tpl.content.firstElementChild.cloneNode(true);
        Object.entries(data).forEach(([k, val]) => { const el = tr.querySelector(`[data-f="${k}"]`); if (el && val !== null) el.value = val; });
        tr.querySelector('[data-f="product_id"]').addEventListener('change', (e) => {
            const opt = e.target.selectedOptions[0];
            if (opt?.dataset.name) { tr.querySelector('[data-f="name"]').value = opt.dataset.name; tr.querySelector('[data-f="unit_price"]').value = opt.dataset.price; }
            recalc();
        });
        tr.querySelectorAll('input').forEach(el => el.addEventListener('input', recalc));
        tr.querySelector('[data-remove]').addEventListener('click', () => { tr.remove(); recalc(); });
        tbody.appendChild(tr);
        recalc();
    };
    document.getElementById('addItem').addEventListener('click', () => addRow());
    document.getElementById('value_from_items').addEventListener('change', recalc);
    (@json(array_values($oldItems))).forEach(addRow);

    customer.addEventListener('change', filterContacts);
    pipeline.addEventListener('change', filterStages);
    stage.addEventListener('change', () => syncStage(true));
    filterContacts(); filterStages(); syncStage(false);
});
</script>
@endpush
