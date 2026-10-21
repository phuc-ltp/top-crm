<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\EmployeeProfile;
use App\Models\Team;
use App\Models\User;
use App\Services\EmployeeKpiService;
use App\Services\ExportService;
use App\Support\Lookup;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Nhân viên (bước 4.2): danh bạ, hồ sơ (KPI, nhóm, quản lý, cấp dưới), sửa hồ sơ, sơ đồ tổ chức, xuất.
 *  - employees.view: danh bạ và thông tin công việc (chức danh, phòng ban, nhóm, liên hệ công việc).
 *  - employees.view_sensitive: thêm thông tin cá nhân (mã hóa trong CSDL) — kể cả khi xuất file.
 *  - employees.update: sửa hồ sơ công việc; sửa thông tin nhạy cảm cần thêm employees.view_sensitive.
 */
class EmployeeController extends Controller
{
    private function orgId(): int
    {
        return (int) app(CurrentOrganization::class)->id();
    }

    /** Nhân viên = thành viên của tổ chức đang làm việc (gồm cả tài khoản tạm ngưng). Ngoài tổ chức → 404. */
    private function member(int $id): User
    {
        return User::inOrganization(null, false)->findOrFail($id);
    }

    private function filtered(Request $request)
    {
        $orgId = $this->orgId();

        return User::inOrganization(null, false)
            ->with(['employeeProfile.branch:id,name', 'employeeProfile.manager:id,name', 'teams:id,name',
                'organizations' => fn ($q) => $q->whereKey($orgId)])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)
                    ->orWhereHas('employeeProfile', fn ($p) => $p->where('job_title', 'like', $term)->orWhere('employee_code', 'like', $term)));
            })
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas('organizations', fn ($o) => $o->whereKey($orgId)
                ->where('organization_user.department_id', $request->integer('department_id'))))
            ->when($request->filled('team_id'), fn ($q) => $q->whereHas('teams', fn ($t) => $t->whereKey($request->integer('team_id'))))
            ->when($request->filled('branch_id'), fn ($q) => $q->whereHas('employeeProfile', fn ($p) => $p->where('branch_id', $request->integer('branch_id'))))
            ->when($request->filled('employment_status'), fn ($q) => $request->employment_status === 'active'
                ? $q->where(fn ($w) => $w->whereDoesntHave('employeeProfile')->orWhereHas('employeeProfile', fn ($p) => $p->where('employment_status', 'active')))
                : $q->whereHas('employeeProfile', fn ($p) => $p->where('employment_status', $request->employment_status)));
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('employees.view'), 403);
        $request->validate(['employment_status' => ['nullable', Rule::in(array_keys(EmployeeProfile::STATUSES))]]);
        $employees = $this->filtered($request)->orderBy('name')->paginate($this->perPage($request))->withQueryString();

        return view('employees.index', [
            'employees' => $employees, 'departments' => Lookup::departments(), 'assignableRoles' => $this->assignableRoles($request),
            'teams' => Team::orderBy('name')->pluck('name', 'id')->all(), 'branches' => Branch::orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function show(Request $request, int $employee)
    {
        abort_unless($request->user()->can('employees.view'), 403);
        $user = $this->member($employee);
        $user->load(['employeeProfile.branch', 'employeeProfile.manager:id,name,avatar', 'teams.leader:id,name']);
        $reports = User::inOrganization(null, false)->whereHas('employeeProfile', fn ($p) => $p->where('manager_id', $user->id))
            ->orderBy('name')->get(['id', 'name', 'avatar']);

        [$from, $to, $period] = EmployeeKpiService::period($request->input('period'));
        $kpi = app(EmployeeKpiService::class)->forUsers([$user->id], $from, $to)[$user->id];

        $canSeePay = $request->user()->id === $user->id || $request->user()->can('payroll.view_all');
        $rates = app(\App\Services\PayRateService::class);

        return view('employees.show', [
            'hourlyRate' => $canSeePay ? $rates->rateFor($user) : null, 'rateSource' => $canSeePay ? $rates->sourceFor($user) : null,
            'user' => $user, 'profile' => $user->employeeProfile, 'reports' => $reports, 'kpi' => $kpi, 'period' => $period,
            'from' => $from, 'to' => $to, 'showSensitive' => $request->user()->can('employees.view_sensitive'),
        ]);
    }

    public function edit(Request $request, int $employee)
    {
        abort_unless($request->user()->can('employees.update'), 403);
        $user = $this->member($employee);
        $profile = $user->employeeProfile ?? new EmployeeProfile(['employment_status' => 'active']);

        return view('employees.form', [
            'user' => $user, 'profile' => $profile, 'branches' => Branch::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'managers' => collect(Lookup::users())->except($user->id)->all(), 'editSensitive' => $request->user()->can('employees.view_sensitive'),
            'payGrades' => $request->user()->can('payroll.manage') ? (function () { app(\App\Services\PayRateService::class)->ensureDefaults();
                return \App\Models\PayGrade::orderByDesc('hourly_rate')->get()->mapWithKeys(fn ($g) => [$g->id => $g->name.' — '.money($g->hourly_rate).'/h'])->all(); })() : null,
        ]);
    }

    public function update(Request $request, int $employee)
    {
        abort_unless($request->user()->can('employees.update'), 403);
        $user = $this->member($employee);
        $sensitive = $request->user()->can('employees.view_sensitive');

        $rules = [
            'employee_code' => ['nullable', 'string', 'max:30', 'alpha_dash', TenantRule::unique('employee_profiles', 'employee_code')->ignore($user->employeeProfile?->id)],
            'job_title' => ['nullable', 'string', 'max:150'],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), TenantRule::member()],
            'branch_id' => ['nullable', 'integer', TenantRule::exists('branches')],
            'hire_date' => ['nullable', 'date', 'before_or_equal:today'],
            'employment_status' => ['required', Rule::in(array_keys(EmployeeProfile::STATUSES))],
            'work_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
        ];
        if ($request->user()->can('payroll.manage')) {
            $rules += [
                'pay_grade_id' => ['nullable', 'integer', TenantRule::exists('pay_grades')],
                'hourly_rate' => ['nullable', 'numeric', 'min:'.config('crm.pay_rate_min'), 'max:'.config('crm.pay_rate_max')],
                'dependents' => ['nullable', 'integer', 'between:0,20'],
            ];
        }
        if ($sensitive) {
            $rules += [
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'personal_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
                'personal_email' => ['nullable', 'email', 'max:255'],
                'home_address' => ['nullable', 'string', 'max:500'],
                'id_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z]+$/'],
                'emergency_contact' => ['nullable', 'string', 'max:255'],
                'hr_notes' => ['nullable', 'string', 'max:5000'],
            ];
        }
        $data = $request->validate($rules, [], [
            'employee_code' => __('Mã nhân viên'), 'manager_id' => __('Quản lý trực tiếp'), 'hire_date' => __('Ngày vào làm'),
            'id_number' => __('Số CCCD / hộ chiếu'), 'date_of_birth' => __('Ngày sinh'),
        ]);
        $this->assertNoManagerLoop($user->id, $data['manager_id'] ?? null);
        if (array_key_exists('dependents', $data)) {
            $data['dependents'] = (int) $data['dependents'];
        }

        DB::transaction(fn () => EmployeeProfile::updateOrCreate(['user_id' => $user->id], $data));

        return redirect()->route('employees.show', $user->id)->with('success', __('Đã cập nhật hồ sơ nhân viên.'));
    }

    /**
     * Tạo nhanh tài khoản nhân viên (bước 5.1): tên, email, điện thoại, vai trò, phòng ban, bậc lương (tùy chọn).
     * Mật khẩu tạm sinh ngẫu nhiên, chỉ hiện MỘT lần cho người tạo; nhân viên bắt buộc đổi ở lần đăng nhập đầu.
     */
    public function storeAccount(Request $request)
    {
        $this->authorize('create', User::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'role' => ['required', Rule::in(array_keys($this->assignableRoles($request)))],
            'department_id' => ['nullable', TenantRule::exists('departments')],
            'job_title' => ['nullable', 'string', 'max:150'],
        ], [], ['name' => __('Họ tên'), 'role' => __('Vai trò'), 'email' => 'Email']);

        $password = \Illuminate\Support\Str::password(14);
        $user = DB::transaction(function () use ($data, $password) {
            $user = User::create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'phone' => $data['phone'] ?? null,
                'password' => $password, 'status' => 'active']);   // tự là thành viên tổ chức hiện tại
            $user->forceFill(['must_change_password' => true])->save();
            $user->assignDepartment($data['department_id'] ?? null);
            $user->syncRoles([$data['role']]);
            EmployeeProfile::create(['user_id' => $user->id, 'job_title' => $data['job_title'] ?? null, 'employment_status' => 'active', 'hire_date' => today()]);

            return $user;
        });
        \App\Services\ActivityLogger::log('created', $user, 'Tạo nhanh tài khoản nhân viên '.$user->email.' (vai trò: '.$data['role'].')');

        // Mật khẩu tạm chỉ nằm trong flash của phiên người tạo (hiện một lần), không ghi log / CSDL dạng đọc được
        return redirect()->route('employees.show', $user->id)->with('temp_password', ['email' => $user->email, 'password' => $password]);
    }

    /** Vai trò được gán (chỉ chủ sở hữu / quản trị nền tảng gán được vai trò chủ sở hữu). */
    private function assignableRoles(Request $request): array
    {
        $canGrantOwner = $request->user()->isPlatformAdmin() || $request->user()->isOrganizationOwner();

        return \Spatie\Permission\Models\Role::where('organization_id', $this->orgId())->orderBy('id')->get()
            ->reject(fn ($r) => $r->name === \App\Support\PermissionCatalog::OWNER && ! $canGrantOwner)
            ->mapWithKeys(fn ($r) => [$r->name => __($r->name)])->all();
    }

    /** Không cho tự làm quản lý của mình hoặc tạo vòng (A quản lý B, B quản lý A...). */
    private function assertNoManagerLoop(int $userId, ?int $managerId): void
    {
        $seen = [];
        while ($managerId) {
            if ($managerId === $userId) {
                throw ValidationException::withMessages(['manager_id' => __('Không thể chọn: quản lý này tạo thành vòng lặp trong sơ đồ báo cáo.')]);
            }
            if (isset($seen[$managerId])) {
                break;
            }
            $seen[$managerId] = true;
            $managerId = EmployeeProfile::where('user_id', $managerId)->value('manager_id');
        }
    }

    /** Sơ đồ tổ chức theo quan hệ quản lý trực tiếp. */
    public function chart(Request $request)
    {
        abort_unless($request->user()->can('employees.view'), 403);
        $people = User::inOrganization()->with(['employeeProfile:id,user_id,job_title,manager_id'])->orderBy('name')->get(['id', 'name', 'avatar']);
        $ids = $people->pluck('id')->flip();
        $children = $people->groupBy(fn ($u) => ($m = $u->employeeProfile?->manager_id) && isset($ids[$m]) ? $m : 0);

        return view('employees.chart', ['children' => $children, 'total' => $people->count()]);
    }

    public function export(Request $request, ExportService $exporter)
    {
        abort_unless($request->user()->can('employees.export'), 403);
        $sensitive = $request->user()->can('employees.view_sensitive');
        $rows = $this->filtered($request)->orderBy('name')->limit(20000)->get()->map(function (User $u) use ($sensitive) {
            $p = $u->employeeProfile;
            $row = [$p?->employee_code, $u->name, $u->email, $p?->job_title, $u->department?->name, $u->teams->pluck('name')->implode(', '),
                $p?->manager?->name, $p?->branch?->name, $p?->hire_date ? vn_date($p->hire_date) : '', label_of(EmployeeProfile::STATUSES, $p?->employment_status ?? 'active'), $p?->work_phone];

            return $sensitive ? array_merge($row, [$p?->date_of_birth, $p?->personal_phone, $p?->personal_email, $p?->home_address, $p?->id_number, $p?->emergency_contact]) : $row;
        });
        \App\Services\ActivityLogger::log('exported', null, 'Xuất danh bạ nhân viên ('.$rows->count().' dòng'.($sensitive ? ', gồm thông tin nhạy cảm' : '').')');

        $headers = [__('Mã nhân viên'), __('Họ tên'), 'Email', __('Chức danh'), __('Phòng ban'), __('Nhóm'), __('Quản lý trực tiếp'), __('Chi nhánh'), __('Ngày vào làm'), __('Trạng thái'), __('Điện thoại công việc')];
        if ($sensitive) {
            $headers = array_merge($headers, [__('Ngày sinh'), __('Điện thoại cá nhân'), __('Email cá nhân'), __('Địa chỉ'), __('Số CCCD / hộ chiếu'), __('Liên hệ khẩn cấp')]);
        }

        return $exporter->download('nhan_vien', $headers, $rows, $request->input('format', 'xlsx'));
    }
}
