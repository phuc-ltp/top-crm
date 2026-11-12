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
    /**
     * Vai trò $actor được phép cấp (theo cấp bậc PermissionCatalog::LEVELS):
     *  - quản trị nền tảng / chủ sở hữu: mọi vai trò; vai trò Chủ sở hữu chỉ khi $allowOwner (quản lý người dùng — không dùng cho link mời / tự đăng ký);
     *  - người khác: chỉ vai trò có cấp THẤP HƠN mình (Quản trị viên không tạo thêm Quản trị viên);
     *  - giữ được vai trò hiện tại của $target (sửa hồ sơ mà không đổi vai trò).
     */
    public static function assignableRoles(User $actor, int $organizationId, bool $allowOwner = false, ?User $target = null): array
    {
        $top = $actor->isPlatformAdmin() || $actor->isOrganizationOwner();
        $mine = $actor->hierarchyLevel();
        $keep = $target ? $target->getRoleNames()->all() : [];

        return Role::where('organization_id', $organizationId)->orderBy('id')->get()
            ->filter(function ($r) use ($top, $mine, $keep, $allowOwner) {
                if ($r->name === PermissionCatalog::OWNER) {
                    return $top && $allowOwner;
                }

                return $top || PermissionCatalog::level($r->name) > $mine || in_array($r->name, $keep, true);
            })
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
