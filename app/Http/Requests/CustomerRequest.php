<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền chi tiết được kiểm tra bằng Policy trong controller
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'company' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Customer::TYPES))],
            'lead_source_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('lead_sources')],
            'price_list_id' => ['nullable', \App\Support\Tenancy\TenantRule::exists('price_lists')],
            'status' => ['required', Rule::in(array_keys(Customer::STATUSES))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at'), \App\Support\Tenancy\TenantRule::member()],
            'notes' => ['nullable', 'string', 'max:5000'],
            'country' => ['nullable', Rule::in(array_keys(config('crm.countries', [])))],
            'segment' => ['nullable', Rule::in(array_keys(Customer::SEGMENTS))],
            'lifecycle_stage' => ['nullable', Rule::in(array_keys(Customer::LIFECYCLE_STAGES))],
            'preferred_channel' => ['nullable', Rule::in(array_keys(Customer::CHANNELS))],
            'marketing_consent' => ['sometimes', 'boolean'],
            // Đồng ý nhận marketing phải ghi rõ nguồn (VD: Form website, Ký hợp đồng, Đồng ý qua điện thoại)
            'marketing_consent_source' => ['nullable', 'required_if_accepted:marketing_consent', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', \App\Support\Tenancy\TenantRule::exists('tags')],
            'confirm_duplicate' => ['nullable', 'boolean'],
            'custom' => ['nullable', 'array'],
        ] + $this->customFieldRules();
    }

    /** Quy tắc cho các trường tùy biến đang hiển thị của tổ chức (custom.{key}). */
    private function customFieldRules(): array
    {
        return \App\Models\CustomFieldDefinition::for('customer')->active()->get()
            ->mapWithKeys(fn ($f) => ['custom.'.$f->key => $f->rules()])->all();
    }

    public function attributes(): array
    {
        $custom = \App\Models\CustomFieldDefinition::for('customer')->active()->pluck('label', 'key')
            ->mapWithKeys(fn ($label, $key) => ['custom.'.$key => $label])->all();

        return $custom + ['name' => __('Tên khách hàng'), 'country' => __('Quốc gia'), 'segment' => __('Phân khúc'), 'lifecycle_stage' => __('Giai đoạn vòng đời'),
            'preferred_channel' => __('Kênh liên lạc ưa thích'), 'marketing_consent_source' => __('Nguồn đồng ý')];
    }
}
