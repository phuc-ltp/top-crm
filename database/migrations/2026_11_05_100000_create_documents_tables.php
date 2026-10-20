<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 4.5 — thư viện tài liệu: tài liệu (danh mục, phạm vi xem, liên kết khách hàng / cơ hội / dự án) và các phiên bản tệp.
 * Tệp lưu ở storage/app/private (không truy cập trực tiếp), chỉ tải / xem qua controller có kiểm tra quyền.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('description', 2000)->nullable();
            $table->string('category', 30)->default('other');
            $table->string('visibility', 20)->default('organization');   // private | team | organization
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('related_type', 30)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'category']);
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->string('note', 500)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['document_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};
