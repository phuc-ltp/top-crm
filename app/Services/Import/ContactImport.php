<?php

namespace App\Services\Import;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;

/** Nhập người liên hệ: gắn khách hàng theo mã (KH00012) hoặc đúng tên — chỉ khách hàng người nhập được xem. */
class ContactImport extends TableImport
{
    public function noun(): string
    {
        return 'người liên hệ';
    }

    public function aliases(): array
    {
        return [
            'full_name' => ['full_name', 'name', 'họ tên', 'ho ten', 'tên', 'ten', 'full name'],
            'position' => ['position', 'chức vụ', 'chuc vu', 'title'],
            'email' => ['email', 'e-mail'],
            'phone' => ['phone', 'điện thoại', 'dien thoai', 'số điện thoại', 'sdt', 'sđt'],
            'company' => ['company', 'công ty', 'cong ty'],
            'customer' => ['customer', 'khách hàng', 'khach hang', 'mã khách hàng', 'customer code'],
            'notes' => ['notes', 'ghi chú', 'ghi chu', 'note'],
        ];
    }

    public function columns(): array
    {
        return [
            'full_name' => __('Họ tên (bắt buộc)'),
            'position' => __('Chức vụ'),
            'email' => 'Email',
            'phone' => __('Số điện thoại'),
            'company' => __('Công ty'),
            'customer' => __('Mã khách hàng (VD: KH00012) hoặc đúng tên khách hàng — để trống nếu chưa gắn'),
            'notes' => __('Ghi chú'),
        ];
    }

    public function sample(): array
    {
        return ['Trần Thị C', 'Trưởng phòng mua hàng', 'tranthic@example.com', '0987654321', 'Công ty ABC', '', ''];
    }

    protected function build(callable $get, User $actor): array|string
    {
        $customerId = null;
        if (($ref = $get('customer')) !== '') {
            $matches = Customer::visibleTo($actor)->where(fn ($q) => $q->where('code', $ref)->orWhere('name', $ref))->limit(2)->pluck('id');
            if ($matches->isEmpty()) {
                return __('không tìm thấy khách hàng ":customer" (hoặc bạn không có quyền với khách hàng này).', ['customer' => $ref]);
            }
            if ($matches->count() > 1) {
                return __('có nhiều khách hàng tên ":customer" — hãy dùng mã khách hàng.', ['customer' => $ref]);
            }
            $customerId = $matches->first();
        }

        return [
            'full_name' => $get('full_name'),
            'position' => $get('position') ?: null,
            'email' => $get('email') ?: null,
            'phone' => $this->phone($get('phone')),
            'company' => $get('company') ?: null,
            'customer_id' => $customerId,
            'notes' => $get('notes') ?: null,
        ];
    }

    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'company' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function attributes(): array
    {
        return ['full_name' => __('Họ tên')];
    }

    protected function duplicate(array $data): ?string
    {
        if ($data['email'] && Contact::whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->exists()) {
            return __('trùng email với người liên hệ đã có.');
        }

        return null;
    }

    protected function persist(array $data, User $actor): void
    {
        Contact::create($data + ['created_by' => $actor->id]);
    }
}
