@extends('layouts.app')
@section('title', __('Link mời đăng ký'))
@section('content')
    <x-page-header :title="__('Link mời đăng ký')" :breadcrumbs="['Người dùng' => route('users.index'), 'Link mời đăng ký' => null]">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#inviteModal"><i class="bi bi-link-45deg me-1"></i>{{ __('Tạo link mời') }}</button>
    </x-page-header>

    @if ($created)
        <div class="card border-success mb-4" style="max-width: 1000px">
            <div class="card-body d-flex flex-wrap gap-4 align-items-center">
                <img src="{{ $created['qr'] }}" alt="{{ __('Mã QR link mời') }}" width="168" height="168" class="border rounded p-1 bg-white">
                <div class="flex-grow-1" style="min-width: 260px">
                    <h6 class="fw-bold text-success mb-1"><i class="bi bi-check-circle me-1"></i>{{ __('Đã tạo link mời') }}</h6>
                    <p class="small text-muted mb-2">{{ __('Vai trò: :role · Tối đa :n người · Hết hạn :date', ['role' => __($created['role']), 'n' => $created['max_uses'], 'date' => $created['expires']]) }}</p>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control font-monospace small" value="{{ $created['url'] }}" readonly id="inviteUrl" aria-label="{{ __('Link mời') }}">
                        <button class="btn btn-dark" type="button" data-copy-invite><i class="bi bi-clipboard me-1"></i>{{ __('Sao chép') }}</button>
                    </div>
                    <div class="small text-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Link chỉ hiển thị một lần — hãy sao chép hoặc quét QR ngay. Ai có link đều đăng ký được cho tới khi hết lượt / hết hạn.') }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="max-width: 1000px">
        <div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>{{ __('Vai trò') }}</th><th>{{ __('Đã đăng ký') }}</th><th>{{ __('Hết hạn') }}</th><th>{{ __('Trạng thái') }}</th><th>{{ __('Ghi chú') }}</th><th>{{ __('Người tạo') }}</th><th class="text-end">{{ __('Thao tác') }}</th></tr></thead>
            <tbody>
            @forelse ($invitations as $inv)
                @php([$stateLabel, $stateColor] = \App\Models\Invitation::STATES[$inv->state])
                <tr>
                    <td class="fw-semibold">{{ __($inv->role) }}</td>
                    <td>{{ $inv->uses }} / {{ $inv->max_uses }}</td>
                    <td class="small">{{ vn_date($inv->expires_at, true) }}</td>
                    <td><span class="badge text-bg-{{ $stateColor }}">{{ __($stateLabel) }}</span></td>
                    <td class="small">{{ $inv->note }}</td>
                    <td class="small">{{ $inv->creator?->name }}</td>
                    <td class="table-actions">
                        @if ($inv->state === 'active')
                            <form method="POST" action="{{ route('invitations.destroy', $inv) }}" class="d-inline" data-confirm="{{ __('Thu hồi link mời này? Người chưa đăng ký sẽ không dùng được link nữa.') }}" data-confirm-button="{{ __('Thu hồi') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-light btn-sm text-danger"><i class="bi bi-x-circle me-1"></i>{{ __('Thu hồi') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty-state icon="bi-link-45deg" :title="__('Chưa có link mời')" :message="__('Tạo link để nhân viên tự đăng ký tài khoản bằng điện thoại, xác thực bằng mã gửi qua email.')" /></td></tr>
            @endforelse
            </tbody></table></div>
        <x-pagination-bar :paginator="$invitations" />
    </div>

    <div class="modal fade" id="inviteModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('invitations.store') }}" class="needs-validation" novalidate>
            @csrf
            <div class="modal-header"><h5 class="modal-title">{{ __('Tạo link mời') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Đóng') }}"></button></div>
            <div class="modal-body">
                <x-select name="role" :label="__('Vai trò của người đăng ký')" :options="$roles" :translate="false" required :value="array_key_exists('Sales Employee', $roles) ? 'Sales Employee' : null" />
                <div class="row">
                    <x-input name="days" type="number" :label="__('Hiệu lực (ngày)')" :value="7" min="1" max="30" required col="col-6" />
                    <x-input name="max_uses" type="number" :label="__('Số người tối đa')" :value="1" min="1" max="200" required col="col-6" />
                </div>
                <x-input name="note" :label="__('Ghi chú')" maxlength="255" :placeholder="__('VD: Nhân viên kinh doanh mới tháng 11')" col="mb-0" />
                <div class="form-text mt-2">{{ __('Người đăng ký phải nhập mã 6 số gửi tới email của họ. Cần cấu hình Email gửi đi (Hệ thống → Email gửi đi) để thư đến được.') }}</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Hủy') }}</button><button class="btn btn-primary">{{ __('Tạo link') }}</button></div>
        </form>
    </div></div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[data-copy-invite]')?.addEventListener('click', async () => {
        const input = document.getElementById('inviteUrl');
        try { await navigator.clipboard.writeText(input.value); toast('success', t('Đã sao chép link mời.')); }
        catch (e) { input.select(); toast('error', t('Không sao chép được, hãy chép thủ công.')); }
    });
    @if ($errors->any()) bootstrap.Modal.getOrCreateInstance(document.getElementById('inviteModal')).show(); @endif
});
</script>
@endpush
