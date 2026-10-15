<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đa tổ chức (multi-tenant): bảng tổ chức + thành viên. Tạo tổ chức mặc định (id = 1)
 * và gán mọi tài khoản hiện có vào đó. Chỉ THÊM bảng/cột, không sửa hay xóa dữ liệu cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->enum('status', ['active', 'suspended'])->default('active')->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['active', 'invited', 'suspended'])->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_organization_id')->nullable()->after('locale')
                ->constrained('organizations')->nullOnDelete();
        });

        // Tổ chức mặc định chứa toàn bộ dữ liệu hiện có
        $name = DB::table('settings')->where('key', 'company_name')->value('value') ?: 'TOP CRM';
        $owner = DB::table('model_has_roles as mr')->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->where('r.name', 'Super Admin')->orderBy('mr.model_id')->value('mr.model_id');
        DB::table('organizations')->insert([
            'id' => 1, 'name' => $name, 'slug' => 'top', 'status' => 'active', 'owner_id' => $owner,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $userIds = DB::table('users')->pluck('id');
        DB::table('organization_user')->insert($userIds->map(fn ($id) => [
            'organization_id' => 1, 'user_id' => $id, 'status' => 'active', 'joined_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ])->all());
        DB::table('users')->update(['current_organization_id' => 1]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_organization_id');
        });
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
    }
};
