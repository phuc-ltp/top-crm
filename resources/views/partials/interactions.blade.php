{{-- Lịch sử tương tác & ghi chú dùng chung. Biến: $subject (model), $type (customer|lead|contact|opportunity), $canAdd --}}
<div class="card">
    <div class="card-header"><span><i class="bi bi-chat-left-text me-2 text-orange"></i>Lịch sử tương tác & ghi chú</span></div>
    <div class="card-body">
        @if ($canAdd ?? false)
            <form method="POST" action="{{ route('interactions.store', [$type, $subject->id]) }}" class="needs-validation mb-4 p-3 rounded-3 bg-light" novalidate>
                @csrf
                <div class="row g-2">
                    <div class="col-md-4">
                        <select name="type" class="form-select form-select-sm" required aria-label="Loại tương tác">
                            @foreach (App\Models\Interaction::TYPES as $k => $t)<option value="{{ $k }}">{{ $t[0] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4"><input type="text" name="subject" class="form-control form-control-sm" placeholder="Tiêu đề (không bắt buộc)" maxlength="255"></div>
                    <div class="col-md-4"><input type="datetime-local" name="occurred_at" class="form-control form-control-sm" value="{{ now()->format('Y-m-d\TH:i') }}" aria-label="Thời điểm"></div>
                    <div class="col-12"><textarea name="content" class="form-control form-control-sm" rows="2" placeholder="Nội dung trao đổi, ghi chú..." required maxlength="5000"></textarea><div class="invalid-feedback">Vui lòng nhập nội dung.</div></div>
                    <div class="col-12 text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Thêm</button></div>
                </div>
            </form>
        @endif

        @if ($subject->interactions->isEmpty())
            <x-empty-state icon="bi-chat-left-text" title="Chưa có tương tác nào" />
        @else
            <ul class="timeline">
                @foreach ($subject->interactions as $it)
                    <li>
                        <span class="tl-icon"><i class="bi {{ App\Models\Interaction::TYPES[$it->type][1] ?? 'bi-dot' }}"></i></span>
                        <div class="d-flex justify-content-between gap-2">
                            <div class="min-w-0">
                                <div class="fw-semibold small">{{ App\Models\Interaction::TYPES[$it->type][0] ?? $it->type }}@if ($it->subject) · {{ $it->subject }}@endif</div>
                                <div class="small text-muted">{{ $it->user?->name ?? 'Hệ thống' }} · {{ vn_date($it->occurred_at ?? $it->created_at, true) }}</div>
                            </div>
                            @if (auth()->id() === $it->user_id || auth()->user()->hasRole('Super Admin'))
                                <form method="POST" action="{{ route('interactions.destroy', $it) }}" data-confirm="Xóa ghi chú này?" data-confirm-button="Xóa">@csrf @method('DELETE')
                                    <button class="btn btn-link btn-sm text-muted p-0" title="Xóa"><i class="bi bi-x-lg"></i></button>
                                </form>
                            @endif
                        </div>
                        <div class="mt-1 small" style="white-space: pre-line">{{ $it->content }}</div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
