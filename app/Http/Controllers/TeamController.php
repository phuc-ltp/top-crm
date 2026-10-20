<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\EmployeeKpiService;
use App\Support\Lookup;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Nhóm làm việc (bước 4.2): danh sách, xem (thành viên + KPI nhóm & từng người), tạo / sửa / xóa (teams.manage). */
class TeamController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('teams.view'), 403);
        $teams = Team::with(['leader:id,name,avatar', 'department:id,name', 'members:id,name,avatar'])->withCount('members')->orderBy('name')->get();

        [$from, $to] = EmployeeKpiService::period('month');
        $service = app(EmployeeKpiService::class);
        $kpi = $service->forUsers($teams->flatMap(fn ($t) => $t->members->pluck('id'))->all(), $from, $to);
        $teamKpi = $teams->mapWithKeys(fn ($t) => [$t->id => $service->sum(array_intersect_key($kpi, array_flip($t->members->pluck('id')->all())))]);

        return view('teams.index', compact('teams', 'teamKpi'));
    }

    public function show(Request $request, Team $team)
    {
        abort_unless($request->user()->can('teams.view'), 403);
        $team->load(['leader:id,name,avatar', 'department:id,name', 'members' => fn ($q) => $q->orderBy('name')]);
        [$from, $to, $period] = EmployeeKpiService::period($request->input('period'));
        $service = app(EmployeeKpiService::class);
        $kpi = $service->forUsers($team->members->pluck('id')->all(), $from, $to);

        return view('teams.show', ['team' => $team, 'kpi' => $kpi, 'total' => $service->sum($kpi), 'period' => $period, 'from' => $from, 'to' => $to]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('teams.manage'), 403);

        return view('teams.form', ['team' => new Team, 'users' => Lookup::users(), 'departments' => Lookup::departments()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('teams.manage'), 403);
        [$data, $members] = $this->validated($request);
        $team = DB::transaction(function () use ($data, $members) {
            $team = Team::create($data);
            $team->members()->sync($members);

            return $team;
        });

        return redirect()->route('teams.show', $team)->with('success', __('Đã tạo nhóm :name.', ['name' => $team->name]));
    }

    public function edit(Request $request, Team $team)
    {
        abort_unless($request->user()->can('teams.manage'), 403);
        $team->load('members:id');

        return view('teams.form', ['team' => $team, 'users' => Lookup::users(), 'departments' => Lookup::departments()]);
    }

    public function update(Request $request, Team $team)
    {
        abort_unless($request->user()->can('teams.manage'), 403);
        [$data, $members] = $this->validated($request, $team);
        DB::transaction(function () use ($team, $data, $members) {
            $team->update($data);
            $team->members()->sync($members);
        });

        return redirect()->route('teams.show', $team)->with('success', __('Đã cập nhật nhóm.'));
    }

    public function destroy(Request $request, Team $team)
    {
        abort_unless($request->user()->can('teams.manage'), 403);
        $team->delete();

        return redirect()->route('teams.index')->with('success', __('Đã xóa nhóm :name.', ['name' => $team->name]));
    }

    /** @return array{0: array, 1: int[]} */
    private function validated(Request $request, ?Team $team = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', TenantRule::unique('teams', 'name')->ignore($team?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'leader_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), TenantRule::member()],
            'department_id' => ['nullable', 'integer', TenantRule::exists('departments')],
            'members' => ['nullable', 'array', 'max:200'],
            'members.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at'), TenantRule::member()],
        ], [], ['name' => __('Tên nhóm'), 'leader_id' => __('Trưởng nhóm'), 'members.*' => __('Thành viên')]);

        $members = array_map('intval', $data['members'] ?? []);
        if (! empty($data['leader_id']) && ! in_array((int) $data['leader_id'], $members, true)) {
            $members[] = (int) $data['leader_id'];   // trưởng nhóm luôn là thành viên
        }
        unset($data['members']);

        return [$data, array_values(array_unique($members))];
    }
}
