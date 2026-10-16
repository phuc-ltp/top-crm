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
        @if (($customFields ?? collect())->isNotEmpty())
            <div class="card mb-4">
                <div class="card-header"><span>{{ __('Thông tin bổ sung') }}</span>
                    @can('settings.manage')<a href="{{ route('settings.custom-fields') }}" class="small">{{ __('Quản lý trường') }}</a>@endcan</div>
                <div class="card-body"><div class="row">
                    @foreach ($customFields as $f)
                        @php($val = old('custom.'.$f->key, $customer->custom_fields[$f->key] ?? null))
                        @switch($f->type)
                            @case('textarea')
                                <x-textarea :name="'custom['.$f->key.']'" :id="'cf_'.$f->key" :label="$f->label" :value="$val" rows="3" maxlength="2000" :required="$f->is_required" col="col-12 mb-3" />
                                @break
                            @case('select')
                                <x-select :name="'custom['.$f->key.']'" :id="'cf_'.$f->key" :label="$f->label" :options="array_combine($f->options ?? [], $f->options ?? [])" :value="$val" :required="$f->is_required" :translate="false" :placeholder="__('— Chọn —')" col="col-md-6 mb-3" />
                                @break
                            @case('checkbox')
                                <div class="col-md-6 mb-3 d-flex align-items-end"><div class="form-check form-switch">
                                    <input type="hidden" name="custom[{{ $f->key }}]" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="custom[{{ $f->key }}]" value="1" id="cf_{{ $f->key }}" @checked((bool) $val)>
                                    <label class="form-check-label" for="cf_{{ $f->key }}">{{ $f->label }}</label></div></div>
                                @break
                            @default
                                <x-input :name="'custom['.$f->key.']'" :id="'cf_'.$f->key" :type="['number' => 'number', 'date' => 'date'][$f->type] ?? 'text'" :label="$f->label" :value="$val" :required="$f->is_required"
                                         :step="$f->type === 'number' ? 'any' : null" :maxlength="$f->type === 'text' ? 255 : null" col="col-md-6 mb-3" />
                        @endswitch
                    @endforeach
                </div></div>
            </div>
        @endif
        <div class="card mb-4">
            <div class="card-header">{{ __('Hồ sơ & liên lạc') }}</div>
            <div class="card-body">
                <div class="row">
                    <x-select name="country" :label="__('Quốc gia')" :options="country_options()" :value="$customer->country" :placeholder="__('— Chưa xác định —')" col="col-md-6 mb-3" />
                    <x-select name="preferred_channel" :label="__('Kênh liên lạc ưa thích')" :options="collect(App\Models\Customer::CHANNELS)->map(fn ($l) => __($l))->all()" :value="$customer->preferred_channel" :placeholder="__('— Chưa xác định —')" col="col-md-6 mb-3" />
                    <x-select name="segment" :label="__('Phân khúc')" :options="App\Models\Customer::SEGMENTS" :value="$customer->segment" :placeholder="__('— Chưa xác định —')" col="col-md-6 mb-3" />
                    <x-select name="lifecycle_stage" :label="__('Giai đoạn vòng đời')" :options="App\Models\Customer::LIFECYCLE_STAGES" :value="$customer->lifecycle_stage" :placeholder="__('— Chưa xác định —')" col="col-md-6 mb-3" />
                    <div class="col-12">
                        <div class="p-3 rounded-3 bg-body-tertiary">
                            <input type="hidden" name="marketing_consent" value="0">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" name="marketing_consent" value="1" id="marketing_consent" @checked(old('marketing_consent', $customer->marketing_consent))>
                                <label class="form-check-label fw-semibold" for="marketing_consent">{{ __('Khách hàng đồng ý nhận thông tin marketing') }}</label>
                            </div>
                            <x-input name="marketing_consent_source" :label="__('Nguồn đồng ý')" :value="$customer->marketing_consent_source" maxlength="100" col="mb-1" :placeholder="__('VD: Form website, Ký hợp đồng, Đồng ý qua điện thoại')" />
                            <div class="form-text">{{ __('Chỉ bật khi khách đã đồng ý rõ ràng. Thời điểm đồng ý / rút lại được hệ thống tự ghi.') }}@if ($customer->marketing_consent_at) {{ __('Lần thay đổi gần nhất: :time.', ['time' => vn_date($customer->marketing_consent_at, true)]) }}@endif</div>
                        </div>
                    </div>
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
