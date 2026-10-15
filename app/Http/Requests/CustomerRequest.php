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
            'status' => ['required', Rule::in(array_keys(Customer::STATUSES))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at'), \App\Support\Tenancy\TenantRule::member()],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', \App\Support\Tenancy\TenantRule::exists('tags')],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => __('Tên khách hàng')];
    }
}
