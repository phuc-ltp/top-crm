<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Tự sinh mã nghiệp vụ dạng PREFIX + id (VD: KH00012) sau khi bản ghi được tạo,
 * tránh xung đột khi nhiều người tạo cùng lúc.
 *
 * Model khai báo: protected static string $codePrefix = 'KH'; (tùy chọn $codeColumn)
 */
trait HasCode
{
    public static function bootHasCode(): void
    {
        static::creating(function ($model) {
            $column = $model->codeColumn();
            if (empty($model->{$column})) {
                $model->{$column} = 'TMP-'.Str::random(20);
            }
        });

        static::created(function ($model) {
            $column = $model->codeColumn();
            if (str_starts_with((string) $model->{$column}, 'TMP-')) {
                $model->{$column} = static::$codePrefix.str_pad((string) $model->getKey(), 5, '0', STR_PAD_LEFT);
                $model->saveQuietly();
            }
        });
    }

    public function codeColumn(): string
    {
        return property_exists($this, 'codeColumn') ? $this->codeColumn : 'code';
    }
}
