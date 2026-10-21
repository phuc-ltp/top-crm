@extends('layouts.app')
@section('title', __('Nhóm tài sản'))
@section('content')
    @php($manage = auth()->user()->can('assets.manage'))
    <x-page-header :title="__('Nhóm tài sản')" :breadcrumbs="['Tài sản' => route('assets.overview'), 'Nhóm tài sản' => null]"
                   :subtitle="__('Số tháng khấu hao của nhóm được gợi ý khi thêm tài sản mới.')" />

    <div class="row g-3">
        <div class="{{ $manage ? 'col-lg-8' : 'col-12' }}"><div class="card">
            @if ($categories->isEmpty())
                <x-empty-state icon="bi-diagram-3" :title="__('Chưa có nhóm tài sản')" />
            @else
                <div class="table-responsive"><table class="table align-middle mb-0">
                    <thead><tr><th>{{ __('Tên nhóm') }}</th><th class="text-end">{{ __('Khấu hao (tháng)') }}</th><th class="text-end">{{ __('Số tài sản') }}</th>@if ($manage)<th></th>@endif</tr></thead>
                    <tbody>
                    @foreach ($categories as $c)
                        <tr>
                            <td>{{ $c->name }}@if ($c->description)<div class="small text-muted">{{ $c->description }}</div>@endif</td>
                            <td class="text-end">{{ $c->depreciation_months ?: __('Không khấu hao') }}</td>
                            <td class="text-end"><a href="{{ route('assets.index', ['category' => $c->id]) }}">{{ $c->assets_count }}</a></td>
                            @if ($manage)
                                <td class="text-end text-nowrap">
                                    <div class="dropdown d-inline">
                                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="{{ __('Sửa') }}" aria-label="{{ __('Sửa') }}"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" action="{{ route('assets.categories.update', $c) }}" class="dropdown-menu dropdown-menu-end p-3 text-start" style="min-width: 280px">@csrf @method('PUT')
                                            <label class="form-label small" for="n{{ $c->id }}">{{ __('Tên nhóm') }}</label>
                                            <input name="name" id="n{{ $c->id }}" class="form-control form-control-sm mb-2" value="{{ $c->name }}" required maxlength="150">
                                            <label class="form-label small" for="m{{ $c->id }}">{{ __('Số tháng khấu hao') }}</label>
                                            <input name="depreciation_months" id="m{{ $c->id }}" type="number" class="form-control form-control-sm mb-2" value="{{ $c->depreciation_months }}" min="0" max="600" required>
                                            <label class="form-label small" for="d{{ $c->id }}">{{ __('Mô tả') }}</label>
                                            <input name="description" id="d{{ $c->id }}" class="form-control form-control-sm mb-2" value="{{ $c->description }}" maxlength="500">
                                            <button class="btn btn-sm btn-primary w-100">{{ __('Lưu') }}</button>
                                        </form>
                                    </div>
                                    @if (! $c->assets_count)<x-delete-button :action="route('assets.categories.destroy', $c)" />@endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div></div>

        @if ($manage)
            <div class="col-lg-4"><div class="card"><div class="card-body">
                <h6 class="mb-3">{{ __('Thêm nhóm') }}</h6>
                <form method="POST" action="{{ route('assets.categories.store') }}" class="needs-validation" novalidate>@csrf
                    <x-input name="name" :label="__('Tên nhóm')" required maxlength="150" />
                    <x-input name="depreciation_months" type="number" :label="__('Số tháng khấu hao')" required value="36" min="0" max="600" />
                    <x-input name="description" :label="__('Mô tả')" maxlength="500" />
                    <button class="btn btn-primary w-100">{{ __('Thêm nhóm') }}</button>
                </form>
            </div></div></div>
        @endif
    </div>
@endsection
