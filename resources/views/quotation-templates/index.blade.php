@extends('layouts.app')
@section('title', __('Mẫu báo giá'))
@section('content')
    <x-page-header :title="__('Mẫu báo giá')" :breadcrumbs="['Báo giá' => route('quotations.index'), 'Mẫu báo giá' => null]"
                   :subtitle="__('Tạo mẫu bằng nút “Lưu làm mẫu” trên trang một báo giá. Nhân viên chọn mẫu khi lập báo giá mới.')" />
    <div class="card">
        @if ($templates->isEmpty())
            <x-empty-state icon="bi-files" :title="__('Chưa có mẫu báo giá')" :message="__('Mở một báo giá chuẩn và bấm “Lưu làm mẫu”.')" />
        @else
            <div class="table-responsive"><table class="table mb-0 align-middle">
                <thead><tr><th>{{ __('Tên mẫu') }}</th><th>{{ __('Điều khoản / ghi chú') }}</th><th class="text-end">{{ __('Số dòng') }}</th><th class="text-center">{{ __('Đang dùng') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
                <tbody>
                @foreach ($templates as $t)
                    <tr>
                        <td style="min-width:220px"><input form="tpl-{{ $t->id }}" name="name" value="{{ $t->name }}" class="form-control form-control-sm" required maxlength="150" aria-label="{{ __('Tên mẫu') }}"></td>
                        <td style="min-width:260px"><textarea form="tpl-{{ $t->id }}" name="notes" rows="2" class="form-control form-control-sm" maxlength="5000" aria-label="{{ __('Điều khoản / ghi chú') }}">{{ $t->notes }}</textarea></td>
                        <td class="text-end">{{ count($t->items ?? []) }}</td>
                        <td class="text-center"><input form="tpl-{{ $t->id }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($t->is_active) aria-label="{{ __('Đang dùng') }}"></td>
                        <td class="table-actions">
                            <a href="{{ route('quotations.create', ['template' => $t->id]) }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Lập báo giá từ mẫu') }}"><i class="bi bi-file-earmark-plus"></i></a>
                            <button form="tpl-{{ $t->id }}" class="btn btn-light btn-icon btn-sm" title="{{ __('Lưu') }}"><i class="bi bi-check2"></i></button>
                            <x-delete-button :action="route('quotation-templates.destroy', $t)" />
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @foreach ($templates as $t)
                <form method="POST" action="{{ route('quotation-templates.update', $t) }}" id="tpl-{{ $t->id }}" class="d-none">@csrf @method('PUT')</form>
            @endforeach
        @endif
    </div>
@endsection
