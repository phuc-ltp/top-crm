<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\PermissionCatalog;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\OrganizationProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Quản trị nền tảng: danh sách tổ chức, tạo tổ chức mới kèm chủ sở hữu, tạm ngưng / kích hoạt. */
class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $organizations = Organization::with('owner:id,name,email')
            ->withCount(['users as members_count' => fn ($q) => $q->where('organization_user.status', 'active')])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')->orWhere('slug', 'like', '%'.$request->q.'%')))
            ->orderBy('id')->paginate(20)->withQueryString();

        return view('platform.organizations.index', compact('organizations'));
    }

    public function create()
    {
        return view('platform.organizations.form', ['organization' => new Organization(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $existingOwner = $request->filled('owner_email')
            ? User::whereRaw('LOWER(email) = ?', [mb_strtolower((string) $request->input('owner_email'))])->first()
            : null;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash', Rule::unique('organizations', 'slug')],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_name' => [$existingOwner ? 'nullable' : 'required', 'string', 'max:255'],
            'owner_password' => [$existingOwner ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ], [], ['name' => __('Tên tổ chức'), 'owner_email' => __('Email chủ sở hữu'), 'owner_name' => __('Họ tên chủ sở hữu'), 'owner_password' => __('Mật khẩu')]);

        $slug = ($data['slug'] ?? null) ?: $this->uniqueSlug($data['name']);

        $organization = DB::transaction(function () use ($data, $slug, $existingOwner) {
            $organization = Organization::create(['name' => $data['name'], 'slug' => $slug, 'status' => 'active']);
            app(OrganizationProvisioner::class)->provision($organization);

            app(CurrentOrganization::class)->run($organization->id, function () use ($organization, $data, $existingOwner) {
                $owner = $existingOwner ?? User::create([
                    'name' => $data['owner_name'], 'email' => mb_strtolower($data['owner_email']),
                    'password' => $data['owner_password'], 'status' => 'active',
                ]);
                $owner->organizations()->syncWithoutDetaching([$organization->id => ['status' => 'active', 'joined_at' => now()]]);
                $owner->unsetRelation('roles');
                $owner->assignRole(PermissionCatalog::OWNER);
                $organization->update(['owner_id' => $owner->id]);
            });

            return $organization;
        });

        ActivityLogger::log('created', null, 'Tạo tổ chức '.$organization->name.' (chủ sở hữu: '.$data['owner_email'].')');

        return redirect()->route('platform.organizations.index')
            ->with('success', __('Đã tạo tổ chức :name. Chủ sở hữu có thể đăng nhập và chuyển sang tổ chức này.', ['name' => $organization->name]));
    }

    public function edit(Organization $organization)
    {
        return view('platform.organizations.form', compact('organization'));
    }

    public function update(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('organizations', 'slug')->ignore($organization->id)],
            'status' => ['required', Rule::in(array_keys(Organization::STATUSES))],
        ], [], ['name' => __('Tên tổ chức')]);

        if ($organization->id === Organization::DEFAULT_ID && $data['status'] !== 'active') {
            return back()->withInput()->with('error', __('Không thể tạm ngưng tổ chức mặc định của hệ thống.'));
        }

        $organization->update($data);
        ActivityLogger::log('updated', null, 'Cập nhật tổ chức '.$organization->name.' ('.$organization->status.')');

        return redirect()->route('platform.organizations.index')->with('success', __('Đã cập nhật tổ chức :name.', ['name' => $organization->name]));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'to-chuc';
        $slug = $base;
        for ($i = 2; Organization::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
