<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\Tenancy\CurrentOrganization;
use RuntimeException;

/**
 * Bản ghi thuộc về một tổ chức: tự gán organization_id theo tổ chức đang làm việc khi tạo mới.
 * (Phạm vi truy vấn theo tổ chức — global scope — được bổ sung ở bước 1.4.)
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::creating(function ($model) {
            if (empty($model->organization_id)) {
                $id = app(CurrentOrganization::class)->id();
                if (! $id && ! $model->organizationIsOptional()) {
                    throw new RuntimeException('Không xác định được tổ chức cho bản ghi '.static::class.'.');
                }
                $model->organization_id = $id;
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
}
