<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quản lý hợp đồng khách hàng: mã, khách hàng / người liên hệ / cơ hội, giá trị, ngày ký – hiệu lực – hết hạn,
 * trạng thái (nháp / đã ký / chấm dứt), tự gia hạn, hợp đồng gia hạn nối tiếp, mốc nhắc hết hạn.
 * Quyền contracts.* theo ma trận mặc định tại thời điểm này. Chỉ thêm bảng / quyền mới.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['contracts.view', 'contracts.view_all', 'contracts.create', 'contracts.update', 'contracts.delete', 'contracts.export'];

    private const GRANTS = [
        'Organization Owner' => self::PERMISSIONS,
        'Organization Administrator' => self::PERMISSIONS,
        'Sales Manager' => self::PERMISSIONS,
        'Sales Employee' => ['contracts.view', 'contracts.create', 'contracts.update'],
        'Finance Manager' => ['contracts.view', 'contracts.view_all', 'contracts.export'],
        'Accountant' => ['contracts.view', 'contracts.view_all'],
        'Read-only Auditor' => ['contracts.view', 'contracts.view_all'],
    ];

    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->nullable();
            $table->string('title');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('sale');                // sale | service | maintenance | partnership | other
            $table->decimal('value', 15, 2)->default(0);
            $table->date('signed_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();                       // trống = không thời hạn
            $table->string('status', 20)->default('draft');             // draft | signed | terminated
            $table->boolean('auto_renew')->default(false);
            $table->foreignId('renewed_from_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->unsignedSmallInteger('reminded_stage')->nullable();
            $table->date('terminated_on')->nullable();
            $table->string('termination_reason', 500)->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status', 'end_date']);
        });

        $now = now();
        foreach (self::PERMISSIONS as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $ids = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->pluck('id', 'name');
        $rows = [];
        foreach (DB::table('roles')->where('guard_name', 'web')->whereIn('name', array_keys(self::GRANTS))->get(['id', 'name']) as $role) {
            foreach (self::GRANTS[$role->name] as $name) {
                $rows[] = ['permission_id' => $ids[$name], 'role_id' => $role->id];
            }
        }
        DB::table('role_has_permissions')->insertOrIgnore($rows);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::dropIfExists('contracts');
    }
};
