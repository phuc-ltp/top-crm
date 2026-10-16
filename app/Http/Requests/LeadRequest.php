<?php

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'lead_source_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('lead_sources')],
            // Giai đoạn theo cấu hình của tổ chức; "converted" chỉ đạt được qua chức năng chuyển đổi
            'status' => ['required', Rule::in($this->route('lead')?->isConverted()
                ? ['converted'] : array_values(array_diff(array_keys(Lead::statuses()), ['converted'])))],
            // Thất bại phải có lý do (danh sách lý do của tổ chức)
            'lost_reason_id' => ['nullable', 'required_if:status,lost', \App\Support\Tenancy\TenantRule::exists('lost_reasons')],
            'lost_note' => ['nullable', 'string', 'max:500'],
            // Hẹn theo dõi tiếp → tạo hoạt động "Việc theo dõi" đã lên lịch cho người phụ trách
            'follow_up_at' => ['nullable', 'date', 'after:now'],
            'confirm_duplicate' => ['nullable', 'boolean'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'auto_score' => ['nullable', 'boolean'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at'), \App\Support\Tenancy\TenantRule::member()],
            'notes' => ['nullable', 'string', 'max:5000'],
            'campaign_ids' => ['nullable', 'array'],
            'campaign_ids.*' => ['integer', \App\Support\Tenancy\TenantRule::exists('campaigns')],
        ];
    }

    public function attributes(): array
    {
        return ['name' => __('Tên lead'), 'lost_reason_id' => __('Lý do thất bại'), 'follow_up_at' => __('Hẹn theo dõi')];
    }
}
