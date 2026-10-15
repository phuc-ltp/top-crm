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
            'lead_source_id' => ['nullable', 'exists:lead_sources,id'],
            // Trạng thái "converted" chỉ đạt được qua chức năng chuyển đổi
            'status' => ['required', Rule::in($this->route('lead')?->isConverted()
                ? ['converted'] : array_diff(array_keys(Lead::STATUSES), ['converted']))],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'auto_score' => ['nullable', 'boolean'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'campaign_ids' => ['nullable', 'array'],
            'campaign_ids.*' => ['integer', 'exists:campaigns,id'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => __('Tên lead')];
    }
}
