<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Marketing nâng cao: chi phí thực tế của chiến dịch, gắn cơ hội với chiến dịch (doanh thu / ROI),
 * mã theo dõi chiến dịch cho link form công khai + thông tin UTM trên lead. Chỉ thêm bảng / cột mới;
 * chiến dịch hiện có được cấp mã theo dõi ngẫu nhiên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->date('spent_on');
            $table->string('category', 20);                       // ads | content | event | tool | staff | other
            $table->decimal('amount', 15, 2);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['campaign_id', 'spent_on']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('tracking_code', 16)->nullable()->after('status');
            $table->unique(['organization_id', 'tracking_code']);
        });
        foreach (DB::table('campaigns')->whereNull('tracking_code')->pluck('id') as $id) {
            DB::table('campaigns')->where('id', $id)->update(['tracking_code' => Str::lower(Str::random(10))]);
        }

        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign']);
        });
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'tracking_code']);
            $table->dropColumn('tracking_code');
        });
        Schema::dropIfExists('campaign_expenses');
    }
};
