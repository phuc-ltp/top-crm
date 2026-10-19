@extends('layouts.app')
@section('title', __('Tùy chọn thông báo'))
@section('content')
    @php($C = App\Support\NotificationCatalog::class)
    @php($wants = fn ($kind, $ch) => App\Notifications\CrmNotification::wants($user, $kind, $ch))
    <x-page-header :title="__('Tùy chọn thông báo')" :breadcrumbs="['Thông báo' => route('notifications.index'), 'Tùy chọn' => null]"
                   :subtitle="__('Chọn loại thông báo bạn muốn nhận. Áp dụng cho riêng tài khoản của bạn.')" />
    @unless ($mailEnabled)
        <div class="alert alert-light border small"><i class="bi bi-envelope-slash me-1"></i>{{ __('Hệ thống chưa cấu hình máy chủ gửi thư nên chưa gửi được email. Lựa chọn email được lưu lại và có tác dụng khi quản trị viên cấu hình máy chủ thư.') }}</div>
    @endunless
    <form method="POST" action="{{ route('notifications.preferences.update') }}">
        @csrf @method('PUT')
        <div class="card" style="max-width: 900px">
            <div class="table-responsive"><table class="table mb-0 align-middle">
                <thead><tr><th>{{ __('Loại thông báo') }}</th><th class="text-center" style="width:130px">{{ __('Trong ứng dụng') }}</th><th class="text-center" style="width:110px">Email</th></tr></thead>
                <tbody>
                @foreach ($C::GROUPS as $group => [$gLabel, $gIcon, $gTone])
                    @php($kinds = array_filter($C::KINDS, fn ($k) => $k[0] === $group))
                    @continue(! $kinds)
                    <tr class="table-light"><td colspan="3" class="small fw-semibold"><span class="avatar avatar-xs tone-{{ $gTone }} me-1"><i class="bi {{ $gIcon }}"></i></span>{{ __($gLabel) }}</td></tr>
                    @foreach ($kinds as $kind => [, $label])
                        <tr>
                            <td class="small">{{ __($label) }}@if ($C::mandatory($kind)) <span class="badge badge-soft-secondary">{{ __('Luôn bật') }}</span>@endif</td>
                            <td class="text-center"><input type="checkbox" class="form-check-input" name="app[{{ $kind }}]" value="1" @checked($wants($kind, 'app')) @disabled($C::mandatory($kind)) aria-label="{{ __('Trong ứng dụng') }}: {{ __($label) }}"></td>
                            <td class="text-center"><input type="checkbox" class="form-check-input" name="mail[{{ $kind }}]" value="1" @checked($wants($kind, 'mail')) aria-label="Email: {{ __($label) }}"></td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table></div>
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="small text-muted">{{ __('Một số loại còn có thể bị tắt cho cả công ty trong Cài đặt › Thông báo nội bộ.') }}</span>
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Lưu') }}</button>
            </div>
        </div>
    </form>
@endsection
