<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tìm kiếm gần đây: riêng từng người dùng, trong từng tổ chức; chỉ giữ 10 từ khóa mới nhất (GlobalSearch::RECENT_LIMIT).
 * Chỉ ghi khi người dùng mở trang kết quả đầy đủ (không ghi từng phím gõ). Chỉ thêm bảng mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query', 100);
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'query']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_histories');
    }
};
