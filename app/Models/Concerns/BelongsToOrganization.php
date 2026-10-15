<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Support\Tenancy\CurrentOrganization;
use LogicException;
use RuntimeException;

/**
 * Bản ghi thuộc về một tổ chức:
 *  - Mọi truy vấn tự giới hạn trong tổ chức đang làm việc (OrganizationScope) → truy cập ID của tổ chức khác = 404.
 *  - Tự gán organization_id khi tạo mới; không cho đổi tổ chức của bản ghi đã có.
 * Truy vấn xuyên tổ chức (chỉ dành cho tác vụ cấp nền tảng) phải gọi rõ ràng withoutOrganizationScope().
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            if (empty($model->organization_id)) {
                $id = app(CurrentOrganization::class)->id();
                if (! $id && ! $model->organizationIsOptional()) {
                    throw new RuntimeException('Không xác định được tổ chức cho bản ghi '.static::class.'.');
                }
                $model->organization_id = $id;
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('organization_id') && $model->getOriginal('organization_id') !== null) {
                throw new LogicException('Không được chuyển bản ghi '.static::class.' sang tổ chức khác.');
            }
        });
    }

    // Cố ý KHÔNG đưa organization_id vào $fillable: không bao giờ nhận giá trị này từ dữ liệu người dùng gửi lên
    // (chống ghi chéo sang tổ chức khác). Mã hệ thống cần gán cụ thể thì dùng forceFill().

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /** Bản ghi cấp nền tảng có thể không thuộc tổ chức nào (vd: nhật ký đăng nhập). */
    public function organizationIsOptional(): bool
    {
        return false;
    }

    /** Truy vấn bỏ qua giới hạn tổ chức — CHỈ dùng cho tác vụ cấp nền tảng (lệnh hệ thống, quản trị nền tảng). */
    public static function withoutOrganizationScope()
    {
        return static::withoutGlobalScope(OrganizationScope::class);
    }
}
