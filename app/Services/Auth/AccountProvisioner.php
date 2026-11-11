<?php

namespace App\Services\Auth;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Tenancy\CurrentOrganization;
use Spatie\Permission\Models\Role;

/**
 * Tạo tài khoản người dùng mới trong một tổ chức (dùng chung cho link mời và duyệt yêu cầu tham gia):
 * email đã xác thực, mật khẩu ĐÃ BĂM sẵn, vai trò theo tổ chức, hồ sơ nhân viên (thử việc).
 * Gọi bên trong DB::transaction để lỗi ở bước nào cũng không để lại tài khoản dở dang.
 */
class AccountProvisioner
{
    /** Vai trò được cấp cho người mới: không bao giờ là Chủ sở hữu; Quản trị viên chỉ khi người cấp là chủ sở hữu / quản trị nền tảng. */
    public static function assignableRoles(User $actor, int $organizationId): array
    {
        $canGrantAdmin = $actor->isPlatformAdmin() || $actor->isOrganizationOwner();

        return Role::where('organization_id', $organizationId)->orderBy('id')->get()
            ->reject(fn ($r) => $r->name === PermissionCatalog::OWNER || ($r->name === 'Organization Administrator' && ! $canGrantAdmin))
            ->mapWithKeys(fn ($r) => [$r->name => __($r->name)])->all();
    }

    /** @param array{name:string, email:string, phone?:?string, password_hash:string, role:string, invited_via_id?:?int} $data */
    public function create(int $organizationId, array $data): User
    {
        return app(CurrentOrganization::class)->run($organizationId, function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'phone' => $data['phone'] ?? null,
                'password' => 'x', 'status' => 'active']);
            // Mật khẩu đã băm sẵn (không băm lần nữa); email đã xác thực bằng mã 6 số
            $user->forceFill(array_filter(['password' => $data['password_hash'], 'email_verified_at' => now(),
                'invited_via_id' => $data['invited_via_id'] ?? null], fn ($v) => $v !== null))->save();
            $user->syncRoles([$data['role']]);
            EmployeeProfile::firstOrCreate(['user_id' => $user->id], ['employment_status' => 'probation', 'hire_date' => today()]);

            return $user;
        });
    }
}
