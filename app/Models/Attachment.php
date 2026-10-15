<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = ['user_id', 'original_name', 'path', 'mime_type', 'size'];

    protected static function booted(): void
    {
        // Tệp được lưu trong storage/app/private, xóa bản ghi thì xóa luôn tệp
        static::deleted(fn (Attachment $a) => Storage::disk('local')->delete($a->path));
    }

    public function attachable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getHumanSizeAttribute(): string
    {
        $size = $this->size;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024) {
                return round($size, 1).' '.$unit;
            }
            $size /= 1024;
        }

        return round($size, 1).' TB';
    }
}
