@extends('layouts.app')
@section('title', __('Chuyển đổi lead'))
@section('content')
    <x-page-header :title="'Chuyển đổi lead: '.$lead->name" :breadcrumbs="['Leads' => route('leads.index'), $lead->code => route('leads.show', $lead), 'Chuyển đổi' => null]" />

    <form method="POST" action="{{ route('leads.convert.store', $lead) }}" class="needs-validation" novalidate style="max-width: 820px">
        @csrf
        <div class="card mb-4">
            <div class="card-header"><span><span class="badge bg-orange me-2">1</span>{{ __('labels.customer') }}</span></div>
            <div class="card-body">
                @if ($matches->isNotEmpty())
                    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Tìm thấy khách hàng có cùng email/điện thoại:') }}
                        @foreach ($matches as $m)<strong>{{ $m->code }} — {{ $m->name }}</strong>@if (! $loop->last), @endif @endforeach. {{ __('Nên liên kết với khách hàng có sẵn.') }}</div>
                @endif
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="mode" id="mode_new" value="new" @checked(old('mode', $matches->isEmpty() ? 'new' : 'existing') === 'new')>
                    <label class="form-check-label fw-semibold" for="mode_new">{{ __('Tạo khách hàng mới') }}</label>
                </div>
                <div class="row ps-4 mb-3" data-mode="new">
                    <x-input name="customer_name" :label="__('Tên khách hàng')" :value="$lead->company ?: $lead->name" maxlength="255" col="col-md-8 mb-2" />
                    <x-select name="customer_type" :label="__('Loại')" :options="App\Models\Customer::TYPES" :value="$lead->company ? 'business' : 'individual'" col="col-md-4 mb-2" />
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="mode" id="mode_existing" value="existing" @checked(old('mode', $matches->isEmpty() ? 'new' : 'existing') === 'existing')>
                    <label class="form-check-label fw-semibold" for="mode_existing">{{ __('Liên kết với khách hàng có sẵn') }}</label>
                </div>
                <div class="ps-4" data-mode="existing">
                    <x-select name="customer_id" :options="$customers" :value="$matches->first()?->id" :placeholder="__('— Chọn khách hàng —')" />
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><span><span class="badge bg-orange me-2">2</span>{{ __('Cơ hội bán hàng') }}</span></div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="create_opportunity" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="create_opportunity" value="1" id="create_opportunity" @checked(old('create_opportunity', '1') === '1')>
                    <label class="form-check-label fw-semibold" for="create_opportunity">{{ __('Tạo cơ hội trong Pipeline') }}</label>
                </div>
                <div class="row" id="oppFields">
                    <x-input name="opportunity_title" :label="__('Tên cơ hội')" :value="__('Cơ hội - :name', ['name' => $lead->company ?: $lead->name])" maxlength="255" col="col-md-12 mb-3" />
                    <x-input name="opportunity_value" type="number" :label="__('Giá trị (₫)')" :value="(int) $lead->estimated_value" min="0" step="1000" col="col-md-6 mb-3" />
                    <x-input name="expected_close_date" type="date" :label="__('Ngày dự kiến chốt')" :value="now()->addDays(30)->format('Y-m-d')" col="col-md-6 mb-3" />
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <a href="{{ route('leads.show', $lead) }}" class="btn btn-light">{{ __('Hủy') }}</a>
            <button class="btn btn-primary" data-loading-text="{{ __('Đang chuyển đổi...') }}"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Chuyển đổi lead') }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const sync = () => {
        const mode = document.querySelector('input[name="mode"]:checked')?.value;
        document.querySelectorAll('[data-mode]').forEach(el => el.style.display = el.dataset.mode === mode ? '' : 'none');
        document.getElementById('customer_id').required = mode === 'existing';
        document.getElementById('customer_name').required = mode === 'new';
        const opp = document.getElementById('create_opportunity').checked;
        document.getElementById('oppFields').style.display = opp ? '' : 'none';
        document.getElementById('opportunity_title').required = opp;
    };
    document.querySelectorAll('input[name="mode"], #create_opportunity').forEach(el => el.addEventListener('change', sync));
    sync();
});
</script>
@endpush
