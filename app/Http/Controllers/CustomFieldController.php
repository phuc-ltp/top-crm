<?php

namespace App\Http\Controllers;

use App\Models\CustomFieldDefinition;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cài đặt → Trường tùy biến: tổ chức tự định nghĩa thêm trường cho khách hàng. */
class CustomFieldController extends Controller
{
    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('settings.manage'), 403);
    }

    private function rules(bool $creating): array
    {
        return array_filter([
            'label' => ['required', 'string', 'max:100'],
            'type' => $creating ? ['required', Rule::in(array_keys(CustomFieldDefinition::TYPES))] : null,
            'options' => ['nullable', 'required_if:type,select', 'string', 'max:2000'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }

    /** "Lựa chọn" nhập mỗi dòng một giá trị → mảng không trùng, bỏ dòng trống. */
    private function parseOptions(?string $raw): array
    {
        return collect(preg_split('/\R/', (string) $raw))->map(fn ($o) => mb_substr(trim($o), 0, 100))
            ->filter(fn ($o) => $o !== '')->unique()->take(50)->values()->all();
    }

    public function index(Request $request)
    {
        $this->authorizeManage($request);

        return view('settings.custom-fields', [
            'fields' => CustomFieldDefinition::for('customer')->get(),
            'organization' => app(\App\Support\Tenancy\CurrentOrganization::class)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate($this->rules(true), [], $this->attributes());
        if (CustomFieldDefinition::where('entity', 'customer')->count() >= CustomFieldDefinition::MAX_PER_ENTITY) {
            return back()->withInput()->with('error', __('Mỗi tổ chức có tối đa :max trường tùy biến.', ['max' => CustomFieldDefinition::MAX_PER_ENTITY]));
        }
        $options = $data['type'] === 'select' ? $this->parseOptions($data['options'] ?? null) : null;
        if ($data['type'] === 'select' && ! $options) {
            return back()->withInput()->withErrors(['options' => __('Hãy nhập ít nhất một lựa chọn.')]);
        }

        CustomFieldDefinition::create([
            'entity' => 'customer', 'key' => CustomFieldDefinition::makeKey($data['label']), 'label' => $data['label'],
            'type' => $data['type'], 'options' => $options, 'is_required' => $request->boolean('is_required'), 'is_active' => true,
            'position' => (int) (CustomFieldDefinition::where('entity', 'customer')->max('position') ?? 0) + 1,
        ]);

        return redirect()->route('settings.custom-fields')->with('success', __('Đã thêm trường :label.', ['label' => $data['label']]));
    }

    public function update(Request $request, CustomFieldDefinition $field)
    {
        $this->authorizeManage($request);
        $data = $request->validate($this->rules(false) + ['type' => ['nullable']], [], $this->attributes());
        $changes = ['label' => $data['label'], 'is_required' => $request->boolean('is_required'), 'is_active' => $request->boolean('is_active'),
            'position' => $data['position'] ?? $field->position];
        if ($field->type === 'select') {
            $options = $this->parseOptions($data['options'] ?? null);
            if (! $options) {
                return back()->withErrors(['options' => __('Hãy nhập ít nhất một lựa chọn.')]);
            }
            $changes['options'] = $options;
        }
        // Khóa và kiểu trường không đổi được (dữ liệu đã nhập phụ thuộc vào chúng)
        $field->update($changes);

        return redirect()->route('settings.custom-fields')->with('success', __('Đã cập nhật trường :label.', ['label' => $field->label]));
    }

    public function destroy(Request $request, CustomFieldDefinition $field)
    {
        $this->authorizeManage($request);
        $field->delete();   // giá trị đã nhập trong hồ sơ khách hàng được giữ nguyên (không hiển thị nữa)

        return redirect()->route('settings.custom-fields')->with('success', __('Đã xóa trường :label. Dữ liệu đã nhập không bị xóa khỏi hồ sơ khách hàng.', ['label' => $field->label]));
    }

    private function attributes(): array
    {
        return ['label' => __('Tên trường'), 'type' => __('Kiểu dữ liệu'), 'options' => __('Các lựa chọn')];
    }
}
