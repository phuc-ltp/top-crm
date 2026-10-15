<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage', User::class);
        $roles = Role::withCount(['users', 'permissions'])->orderBy('id')->get();

        return view('roles.index', ['roles' => $roles, 'defaults' => PermissionCatalog::ROLES]);
    }

    public function create()
    {
        $this->authorize('manage', User::class);

        return view('roles.form', ['role' => new Role, 'assigned' => [], 'catalog' => PermissionCatalog::MODULES, 'labels' => PermissionCatalog::ACTION_LABELS]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage', User::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')],
            'permissions' => ['nullable', 'array'], 'permissions.*' => [Rule::in(PermissionCatalog::all())],
        ], [], ['name' => 'Tên vai trò']);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        ActivityLogger::log('created', null, 'Tạo vai trò '.$role->name);

        return redirect()->route('roles.index')->with('success', 'Đã tạo vai trò '.$role->name.'.');
    }

    public function edit(Role $role)
    {
        $this->authorize('manage', User::class);

        return view('roles.form', [
            'role' => $role, 'assigned' => $role->permissions->pluck('name')->all(),
            'catalog' => PermissionCatalog::MODULES, 'labels' => PermissionCatalog::ACTION_LABELS,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $this->authorize('manage', User::class);
        abort_if($role->name === 'Super Admin', 422, 'Super Admin luôn có toàn quyền, không cần chỉnh.');
        $isDefault = in_array($role->name, PermissionCatalog::ROLES, true);

        $data = $request->validate([
            'name' => [$isDefault ? 'nullable' : 'required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['nullable', 'array'], 'permissions.*' => [Rule::in(PermissionCatalog::all())],
        ], [], ['name' => 'Tên vai trò']);

        if (! $isDefault && ! empty($data['name'])) {
            $role->update(['name' => $data['name']]);
        }
        $before = $role->permissions->pluck('name')->all();
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $after = $data['permissions'] ?? [];
        ActivityLogger::log('updated', null, 'Cập nhật quyền vai trò '.$role->name, [
            'added' => array_values(array_diff($after, $before)), 'removed' => array_values(array_diff($before, $after)),
        ]);

        return redirect()->route('roles.index')->with('success', 'Đã cập nhật quyền cho vai trò '.$role->name.'.');
    }

    public function destroy(Role $role)
    {
        $this->authorize('manage', User::class);
        abort_if(in_array($role->name, PermissionCatalog::ROLES, true), 422, 'Không thể xóa vai trò mặc định.');
        abort_if($role->users()->exists(), 422, 'Vai trò đang được gán cho người dùng, hãy chuyển họ sang vai trò khác trước.');
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Đã xóa vai trò.');
    }
}
