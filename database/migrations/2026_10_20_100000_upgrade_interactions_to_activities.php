<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nâng "ghi chú tương tác" thành HOẠT ĐỘNG: có thể lên lịch (planned) cho người phụ trách, đánh dấu xong kèm kết quả,
 * được nhắc khi đến hạn. Bản ghi cũ = hoạt động đã xong (status done). Chỉ thêm cột + mở rộng danh sách loại.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE interactions MODIFY type ENUM('note','call','email','meeting','follow_up','other') NOT NULL DEFAULT 'note'");

        Schema::table('interactions', function (Blueprint $table) {
            $table->text('content')->nullable()->change();                       // lịch hẹn có thể chưa có ghi chú
            $table->string('status', 20)->default('done')->after('type');          // planned | done
            $table->timestamp('scheduled_at')->nullable()->after('occurred_at');    // thời điểm hẹn (hoạt động lên lịch)
            $table->timestamp('completed_at')->nullable()->after('scheduled_at');
            $table->foreignId('assigned_to')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('outcome', 1000)->nullable()->after('content');          // kết quả khi hoàn thành
            $table->timestamp('reminded_at')->nullable()->after('completed_at');

            $table->index(['organization_id', 'status', 'scheduled_at']);
            $table->index(['assigned_to', 'status']);
        });

        DB::table('interactions')->update(['completed_at' => DB::raw('COALESCE(occurred_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status', 'scheduled_at']);
            $table->dropForeign(['assigned_to']);          // gỡ khóa ngoại trước: chỉ mục (assigned_to, status) đang phục vụ khóa ngoại
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropColumn('assigned_to');
            $table->dropColumn(['status', 'scheduled_at', 'completed_at', 'outcome', 'reminded_at']);
        });
        DB::table('interactions')->whereNull('content')->update(['content' => '']);
        Schema::table('interactions', fn (Blueprint $table) => $table->text('content')->nullable(false)->change());
        // Hoạt động loại "theo dõi" được chuyển thành "khác" trước khi thu hẹp danh sách loại
        DB::table('interactions')->where('type', 'follow_up')->update(['type' => 'other']);
        DB::statement("ALTER TABLE interactions MODIFY type ENUM('note','call','email','meeting','other') NOT NULL DEFAULT 'note'");
    }
};
