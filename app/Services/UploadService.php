<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lưu tệp tải lên an toàn: tên tệp ngẫu nhiên, phần mở rộng lấy theo MIME thực tế,
 * ảnh công khai trong storage/app/public, tệp đính kèm trong storage/app/private (tải qua controller có kiểm tra quyền).
 */
class UploadService
{
    public function replacePublicImage(UploadedFile $file, string $folder, ?string $oldPath = null): string
    {
        $path = $file->storeAs($folder, Str::uuid().'.'.$file->guessExtension(), 'public');
        $this->deletePublic($oldPath);

        return $path;
    }

    public function deletePublic(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function attach(Model $model, UploadedFile $file): Attachment
    {
        $extension = $file->guessExtension() ?: 'bin';
        $path = $file->storeAs('attachments/'.date('Y/m'), Str::uuid().'.'.$extension, 'local');

        return $model->attachments()->create([
            'user_id' => Auth::id(),
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}
