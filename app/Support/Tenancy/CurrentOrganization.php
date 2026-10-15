<?php

namespace App\Support\Tenancy;

use App\Models\Organization;

/**
 * Tổ chức đang làm việc trong yêu cầu hiện tại.
 *
 * Thứ tự xác định: giá trị đã đặt rõ ràng (middleware / job / lệnh) → tổ chức hiện tại của người dùng đăng nhập
 * → tổ chức mặc định (cài đặt một doanh nghiệp). Dùng để tự gán organization_id khi tạo dữ liệu mới.
 */
class CurrentOrganization
{
    private ?int $id = null;

    public function set(?int $id): void
    {
        $this->id = $id;
    }

    public function id(): ?int
    {
        if ($this->id) {
            return $this->id;
        }
        $user = auth()->user();
        // Yêu cầu bằng khóa API: luôn là tổ chức của khóa (không phải tổ chức đang chọn trên web)
        $token = $user && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;
        if ($token instanceof \App\Models\ApiToken) {
            return $token->organization_id ? (int) $token->organization_id : null;
        }
        if ($user?->current_organization_id) {
            return (int) $user->current_organization_id;
        }

        return Organization::query()->whereKey(Organization::DEFAULT_ID)->exists() ? Organization::DEFAULT_ID : null;
    }

    public function get(): ?Organization
    {
        $id = $this->id();

        return $id ? Organization::find($id) : null;
    }

    /** Chạy đoạn mã trong ngữ cảnh một tổ chức cụ thể rồi khôi phục ngữ cảnh cũ (dùng cho job, seeder, test). */
    public function run(int $organizationId, callable $callback): mixed
    {
        $previous = $this->id;
        $this->id = $organizationId;
        try {
            return $callback();
        } finally {
            $this->id = $previous;
        }
    }
}
