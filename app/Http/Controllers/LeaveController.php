<?php

namespace App\Http\Controllers;

use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Notifications\CrmNotification;
use App\Services\ActivityLogger;
use App\Services\LeaveService;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Đơn nghỉ phép: nhân viên gửi; quản lý trực tiếp hoặc người có quyền lương duyệt / từ chối.
 * Tab "Của tôi" (mọi người), "Chờ tôi duyệt" (quản lý), "Tất cả" (payroll.view_all).
 */
class LeaveController extends Controller
{
    public function __construct(private LeaveService $leave) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $request->validate(['tab' => ['nullable', Rule::in(['mine', 'approve', 'all'])], 'year' => ['nullable', 'integer', 'between:2000,2100'],
            'status' => ['nullable', Rule::in(array_keys(LeaveRequest::STATUSES))], 'user_id' => ['nullable', 'integer']]);
        $year = $request->integer('year') ?: now()->year;
        $reports = EmployeeProfile::where('manager_id', $user->id)->pluck('user_id');
        $canAll = $user->can('payroll.view_all');
        $tab = $request->input('tab', 'mine');
        abort_if($tab === 'all' && ! $canAll, 403);

        $items = LeaveRequest::with(['user:id,name,avatar', 'approver:id,name'])->whereYear('start_date', $year)
            ->when($tab === 'mine', fn ($q) => $q->where('user_id', $user->id))
            ->when($tab === 'approve', fn ($q) => $user->can('payroll.manage') ? $q->where('user_id', '!=', $user->id) : $q->whereIn('user_id', $reports))
            ->when($tab === 'all' && $request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('start_date')->paginate($this->perPage($request))->withQueryString();

        return view('leave.index', [
            'items' => $items, 'tab' => $tab, 'year' => $year, 'canAll' => $canAll,
            'canApprove' => $user->can('payroll.manage') || $reports->isNotEmpty(),
            'pendingForMe' => LeaveRequest::where('status', 'pending')->when(! $user->can('payroll.manage'), fn ($q) => $q->whereIn('user_id', $reports))->where('user_id', '!=', $user->id)->count(),
            'balance' => ['quota' => $this->leave->quota($user), 'used' => $this->leave->used($user, $year), 'pending' => $this->leave->used($user, $year, ['pending'])],
            'holidays' => $this->leave->holidays("$year-01-01", "$year-12-31")->values(),
            'users' => $canAll ? Lookup::users() : [],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(LeaveRequest::TYPES))],
            'start_date' => ['required', 'date', 'after_or_equal:'.now()->subDays(31)->toDateString(), 'before_or_equal:'.now()->addYear()->toDateString()],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'part' => ['required', Rule::in(array_keys(LeaveRequest::PARTS))],
            'reason' => ['required', 'string', 'max:1000'],
        ], [], ['type' => __('Loại nghỉ'), 'start_date' => __('Từ ngày'), 'end_date' => __('Đến ngày'), 'part' => __('Buổi'), 'reason' => __('Lý do')]);
        $start = Carbon::parse($data['start_date'])->toDateString();
        $end = Carbon::parse($data['end_date'])->toDateString();
        $days = DB::transaction(function () use ($user, $data, $start, $end) {
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();   // tránh hai đơn song song vượt quỹ
            $days = $this->leave->validateRequest($user, $data['type'], $start, $end, $data['part']);
            LeaveRequest::create(['user_id' => $user->id, 'type' => $data['type'], 'start_date' => $start, 'end_date' => $end, 'part' => $data['part'],
                'days' => $days, 'reason' => $data['reason'], 'status' => 'pending']);

            return $days;
        });
        foreach ($this->leave->approversFor($user) as $u) {
            CrmNotification::send($u, 'leave_request', __(':name xin nghỉ :t từ :a đến :b (:d ngày).', ['name' => $user->name, 't' => mb_strtolower(label_of(LeaveRequest::TYPES, $data['type'])),
                'a' => vn_date($start), 'b' => vn_date($end), 'd' => (float) $days]), route('leave.index', ['tab' => 'approve']), 'bi-calendar-x');
        }

        return redirect()->route('leave.index')->with('success', __('Đã gửi đơn nghỉ phép.'));
    }

    public function approve(Request $request, LeaveRequest $leave)
    {
        return $this->decide($request, $leave, 'approved');
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        return $this->decide($request, $leave, 'rejected');
    }

    public function cancel(Request $request, LeaveRequest $leave)
    {
        abort_unless($leave->user_id === $request->user()->id, 404);
        abort_unless($leave->status === 'pending', 422, __('Chỉ hủy được đơn đang chờ duyệt.'));
        $leave->update(['status' => 'cancelled']);

        return back()->with('success', __('Đã hủy đơn nghỉ phép.'));
    }

    private function decide(Request $request, LeaveRequest $leave, string $status)
    {
        abort_unless($this->leave->canDecide($request->user(), $leave), 403);
        abort_unless($leave->status === 'pending', 422, __('Đơn này đã được xử lý.'));
        $data = $request->validate(['decision_note' => [$status === 'rejected' ? 'required' : 'nullable', 'string', 'max:500']], [], ['decision_note' => __('Ghi chú')]);
        if ($status === 'approved' && $leave->type === 'annual') {
            $this->leave->assertBalance($leave->user, $leave->start_date->year, (string) $leave->days, ['approved']);
        }
        $leave->forceFill(['status' => $status, 'approver_id' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $data['decision_note'] ?? null])->save();
        ActivityLogger::log('updated', $leave, ($status === 'approved' ? 'Duyệt' : 'Từ chối').' đơn nghỉ của '.$leave->user?->name.' từ '.vn_date($leave->start_date));
        CrmNotification::send($leave->user, 'leave_decision', $status === 'approved'
            ? __('Đơn nghỉ từ :a đến :b đã được duyệt.', ['a' => vn_date($leave->start_date), 'b' => vn_date($leave->end_date)])
            : __('Đơn nghỉ từ :a bị từ chối: :n', ['a' => vn_date($leave->start_date), 'n' => $leave->decision_note]), route('leave.index'), 'bi-calendar-check');

        return back()->with('success', $status === 'approved' ? __('Đã duyệt đơn nghỉ phép.') : __('Đã từ chối đơn nghỉ phép.'));
    }
}
