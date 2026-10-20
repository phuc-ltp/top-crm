<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Một phiên bản tệp của tài liệu (bất biến: tải bản mới thay vì sửa bản cũ). */
class DocumentVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['document_id', 'version', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'note', 'uploaded_by'];

    protected static function booted(): void
    {
        // Xóa phiên bản (khi xóa vĩnh viễn tài liệu) thì xóa luôn tệp trong storage/app/private
        static::deleted(fn (DocumentVersion $v) => Storage::disk('local')->delete($v->path));
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
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

    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, Document::PREVIEWABLE, true);
    }
}
