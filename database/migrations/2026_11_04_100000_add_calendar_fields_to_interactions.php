<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 4.4 — lịch hẹn: giờ kết thúc, địa điểm / link họp, người tham dự nội bộ. Chỉ thêm cột (nullable) và bảng mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->timestamp('ends_at')->nullable()->after('scheduled_at');
            $table->string('location', 255)->nullable()->after('ends_at');
        });

        Schema::create('interaction_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['interaction_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_attendees');
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropColumn(['ends_at', 'location']);
        });
    }
};
