<?php

namespace App\Services\Import;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use App\Services\LeadService;
use Illuminate\Validation\Rule;

/** Nhập lead: cùng quy tắc như form tạo lead; lưu qua LeadService (chấm điểm, nhật ký). Người nhập là người phụ trách. */
class LeadImport extends TableImport
{
    private ?array $sources = null;

    public function __construct(private LeadService $leads) {}

    public function noun(): string
    {
        return 'lead';
    }

    public function aliases(): array
    {
        return [
            'name' => ['name', 'tên', 'ten', 'tên lead', 'họ tên', 'ho ten', 'lead name'],
            'company' => ['company', 'công ty', 'cong ty'],
            'email' => ['email', 'e-mail'],
            'phone' => ['phone', 'điện thoại', 'dien thoai', 'số điện thoại', 'sdt', 'sđt'],
            'source' => ['source', 'nguồn', 'nguon'],
            'status' => ['status', 'giai đoạn', 'giai doan', 'trạng thái', 'trang thai', 'stage'],
            'estimated_value' => ['estimated_value', 'giá trị dự kiến', 'gia tri du kien', 'value', 'giá trị'],
            'notes' => ['notes', 'ghi chú', 'ghi chu', 'note'],
        ];
    }

    public function columns(): array
    {
        return [
            'name' => __('Tên lead (bắt buộc)'),
            'company' => __('Công ty'),
            'email' => 'Email',
            'phone' => __('Số điện thoại'),
            'source' => __('Tên nguồn (VD: Website, Facebook)'),
            'status' => __('Mã hoặc tên giai đoạn lead (để trống: giai đoạn đầu tiên). Không nhập được "Đã chuyển đổi" / "Mất".'),
            'estimated_value' => __('Giá trị dự kiến (VD: 15000000 hoặc 15.000.000)'),
            'notes' => __('Ghi chú'),
        ];
    }

    public function sample(): array
    {
        return ['Nguyễn Văn B', 'Công ty XYZ', 'nguyenvanb@example.com', '0912345678', 'Website', '', '15000000', 'Quan tâm gói doanh nghiệp'];
    }

    /** Giai đoạn được phép nhập: mọi giai đoạn trừ "đã chuyển đổi" (chỉ qua chức năng chuyển đổi) và "mất" (cần lý do). */
    private function allowedStatuses(): array
    {
        return array_diff_key(Lead::statuses(), array_flip(['converted', 'lost']));
    }

    protected function build(callable $get, User $actor): array|string
    {
        $this->sources ??= LeadSource::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])->all();
        $allowed = $this->allowedStatuses();
        $status = $get('status');
        if ($status === '') {
            $status = array_key_first($allowed);
        } elseif (! isset($allowed[$status])) {
            $byName = collect($allowed)->search(fn ($s) => mb_strtolower($s[0]) === mb_strtolower($status) || mb_strtolower(__($s[0])) === mb_strtolower($status));
            if ($byName === false) {
                return __('giai đoạn ":status" không hợp lệ hoặc không được nhập bằng tệp.', ['status' => $status]);
            }
            $status = $byName;
        }
        if ($get('source') !== '' && ! isset($this->sources[mb_strtolower($get('source'))])) {
            return __('không có nguồn ":source" trong tổ chức.', ['source' => $get('source')]);
        }

        return [
            'name' => $get('name'),
            'company' => $get('company') ?: null,
            'email' => $get('email') ?: null,
            'phone' => $this->phone($get('phone')),
            'lead_source_id' => $this->sources[mb_strtolower($get('source'))] ?? null,
            'status' => $status,
            'estimated_value' => $this->money($get('estimated_value')),
            'notes' => $get('notes') ?: null,
            'assigned_to' => $actor->id,
        ];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'status' => ['required', Rule::in(array_keys($this->allowedStatuses()))],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function attributes(): array
    {
        return ['name' => __('Tên lead'), 'estimated_value' => __('Giá trị dự kiến')];
    }

    protected function duplicate(array $data): ?string
    {
        $dups = $this->leads->findDuplicates($data['email'], $data['phone']);
        if ($dups['leads']->isNotEmpty()) {
            return __('trùng email/điện thoại với lead đã có (:name).', ['name' => $dups['leads']->first()->name]);
        }
        if ($dups['customers']->isNotEmpty()) {
            return __('trùng email/điện thoại với khách hàng đã có (:name).', ['name' => $dups['customers']->first()->name]);
        }

        return null;
    }

    protected function persist(array $data, User $actor): void
    {
        $this->leads->save(new Lead, $data, $actor);
    }
}
