<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogger;

/**
 * Ghi nhật ký tạo / cập nhật / xóa vào bảng activity_logs.
 * Không bao giờ ghi các trường nhạy cảm (mật khẩu, token).
 */
trait LogsActivity
{
    protected static array $neverLog = ['password', 'remember_token', 'updated_at', 'created_at'];

    public static function bootLogsActivity(): void
    {
        static::created(fn ($model) => ActivityLogger::log('created', $model, 'Tạo mới '.$model->activityLabel()));

        static::updated(function ($model) {
            $changes = collect($model->getChanges())->except(array_merge(static::$neverLog, $model->getHidden()));
            if ($changes->isEmpty()) {
                return;
            }
            $old = collect($model->getOriginal())->only($changes->keys());
            ActivityLogger::log('updated', $model, 'Cập nhật '.$model->activityLabel(), [
                'old' => $old->all(),
                'new' => $changes->all(),
            ]);
        });

        static::deleted(fn ($model) => ActivityLogger::log('deleted', $model, 'Xóa '.$model->activityLabel()));
    }

    public function activityLabel(): string
    {
        $name = $this->name ?? $this->title ?? $this->full_name ?? $this->subject ?? $this->number ?? null;
        $code = $this->code ?? $this->sku ?? null;

        return trim(static::activityTypeLabel(class_basename($this)).' '.($code ? "[$code] " : '').($name ?? '#'.$this->getKey()));
    }

    /** Tên loại đối tượng hiển thị cho người dùng trong nhật ký. */
    public static function activityTypeLabel(string $class): string
    {
        return [
            'User' => 'người dùng', 'Customer' => 'khách hàng', 'Contact' => 'người liên hệ', 'Lead' => 'lead',
            'Opportunity' => 'cơ hội', 'Task' => 'công việc', 'Product' => 'sản phẩm', 'Quotation' => 'báo giá',
            'SalesOrder' => 'đơn hàng', 'Invoice' => 'hóa đơn', 'Payment' => 'thanh toán', 'Campaign' => 'chiến dịch',
            'SupportTicket' => 'ticket', 'Department' => 'phòng ban', 'CustomFieldDefinition' => 'trường tùy biến',
        ][$class] ?? $class;
    }
}
