@if (session('duplicates'))
    <div class="alert alert-warning">
        <div class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Khách hàng có thể bị trùng:') }}</div>
        <ul class="mb-2 small">
            @foreach (session('duplicates') as $d)
                <li><strong>{{ $d->code }} — {{ $d->name }}</strong> · {{ $d->email }} · {{ $d->phone }} @if ($d->assignee) ({{ __('phụ trách: :name', ['name' => $d->assignee->name]) }}) @endif</li>
            @endforeach
        </ul>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="confirm_duplicate" value="1" id="confirm_duplicate">
            <label class="form-check-label small fw-semibold" for="confirm_duplicate">{{ __('Tôi đã kiểm tra — vẫn lưu khách hàng này') }}</label>
        </div>
    </div>
@endif
<div id="dupLive" class="alert alert-warning small d-none"></div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">{{ __('Thông tin khách hàng') }}</div>
            <div class="card-body">
                <div class="row">
                    <x-input name="name" :label="__('Tên khách hàng')" :value="$customer->name" required maxlength="255" col="col-md-8 mb-3" />
                    <x-select name="type" :label="__('Loại khách hàng')" :options="App\Models\Customer::TYPES" :value="$customer->type" required col="col-md-4 mb-3" />
                    <x-input name="phone" :label="__('Số điện thoại')" :value="$customer->phone" maxlength="30" pattern="[0-9+\-\s().]{6,30}" col="col-md-6 mb-3" data-dup-check />
                    <x-input name="email" type="email" label="Email" :value="$customer->email" maxlength="255" col="col-md-6 mb-3" data-dup-check />
                    <x-input name="company" :label="__('Công ty')" :value="$customer->company" maxlength="255" col="col-md-6 mb-3" />
                    <x-select name="lead_source_id" :label="__('Nguồn khách hàng')" :options="$sources" :value="$customer->lead_source_id" :placeholder="__('— Chọn nguồn —')" col="col-md-6 mb-3" />
                    <x-input name="address" :label="__('Địa chỉ')" :value="$customer->address" maxlength="500" col="col-12 mb-3" />
                    <x-textarea name="notes" :label="__('Ghi chú')" :value="$customer->notes" rows="4" maxlength="5000" col="col-12 mb-0" />
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">{{ __('Phân loại & phụ trách') }}</div>
            <div class="card-body">
                <x-select name="status" :label="__('Trạng thái')" :options="App\Models\Customer::STATUSES" :value="$customer->status" required />
                @can('customers.assign')
                    <x-select name="assigned_to" :label="__('Nhân viên phụ trách')" :options="$users" :value="$customer->assigned_to" :placeholder="__('— Chưa phân công —')" />
                @endcan
                <x-select name="tags[]" id="tags" :label="__('Thẻ (giữ Ctrl để chọn nhiều)')" :options="$tags" :value="$customer->exists ? $customer->tags->pluck('id')->all() : []" multiple size="5" />
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-check2 me-1"></i>{{ __('Lưu khách hàng') }}</button>
            <a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-light">{{ __('Hủy') }}</a>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('dupLive');
    const fields = document.querySelectorAll('[data-dup-check]');
    const check = async () => {
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        if (!email && !phone) { box.classList.add('d-none'); return; }
        try {
            const params = new URLSearchParams({ email, phone, except: '{{ $customer->id }}' });
            const data = await api('{{ route('customers.check-duplicate') }}?' + params);
            if (!data.duplicates.length) { box.classList.add('d-none'); return; }
            box.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i><strong>' + t('Cảnh báo trùng:') + '</strong> ' +
                data.duplicates.map(d => (d.url ? `<a href="${d.url}" target="_blank">` : '') + `${d.code} — ${d.name}` + (d.url ? '</a>' : '') + (d.assignee ? ` (${t('phụ trách:')} ${d.assignee})` : '')).join('; ');
            box.classList.remove('d-none');
        } catch (e) { /* bỏ qua */ }
    };
    fields.forEach(f => f.addEventListener('blur', check));
});
</script>
@endpush
