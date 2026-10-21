<?php

namespace App\Http\Controllers;

use App\Models\EmployeeProfile;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Services\ActivityLogger;
use App\Services\OvertimeService;
use App\Support\Decimal;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Đề nghị tăng ca (bước 5.3): nhân viên tạo đơn; quản lý trực tiếp hoặc người có quyền lương duyệt / từ chối.
 * Tab "Của tôi" (mọi người), "Chờ tôi duyệt" (quản lý), "Tất cả" (payroll.view_all).
 */
class OvertimeController extends Controller
{
    public function __construct(private OvertimeService $overtime) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $request->validate(['tab' => ['nullable', Rule::in(['mine', 'approve', 'all'])], 'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(array_keys(OvertimeRequest::STATUSES))]]);
        $month = Carbon::createFromFormat('Y-m-d', ($request->input('month') ?: now()->format('Y-m')).'-01');
        $reports = EmployeeProfile::where('manager_id', $user->id)->pluck('user_id');
        $canAll = $user->can('payroll.view_all');
        $tab = $request->input('tab', 'mine');
        abort_if($tab === 'all' && ! $canAll, 403);

        $query = OvertimeRequest::with(['user:id,name,avatar', 'approver:id,name'])
            ->whereBetween('work_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->when($tab === 'mine', fn ($q) => $q->where('user_id', $user->id))
            ->when($tab === 'approve', fn ($q) => $user->can('payroll.manage') ? $q->where('user_id', '!=', $user->id) : $q->whereIn('user_id', $reports))
            ->when($tab === 'all' && $request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status));
        $items = (clone $query)->orderByDesc('work_date')->orderBy('start_time')->paginate($this->perPage($request))->withQueryString();
        $sum = (clone $query)->where('status', 'approved')->get();

        return view('overtime.index', [
            'items' => $items, 'tab' => $tab, 'month' => $month, 'canAll' => $canAll,
            'canApprove' => $user->can('payroll.manage') || $reports->isNotEmpty(),
            'pendingForMe' => OvertimeRequest::where('status', 'pending')->when(! $user->can('payroll.manage'), fn ($q) => $q->whereIn('user_id', $reports))->where('user_id', '!=', $user->id)->count(),
            'totals' => ['hours' => Decimal::sum($sum->pluck('hours')), 'amount' => Decimal::sum($sum->map(fn ($r) => Decimal::mul($r->hours, $r->rate)))],
            'users' => $canAll ? Lookup::users() : [], 'levelRates' => OvertimeService::levelRates(),
            'window' => [\App\Services\AttendanceService::setting('work_ot_start'), \App\Services\AttendanceService::setting('work_ot_end')],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'work_date' => ['required', 'date', 'after_or_equal:'.now()->subDays(31)->toDateString(), 'before_or_equal:'.now()->addDays(60)->toDateString()],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'level' => ['required', Rule::in(array_keys(OvertimeRequest::LEVELS))],
            'reason' => ['required', 'string', 'max:1000'],
        ], [], ['work_date' => __('Ngày tăng ca'), 'level' => __('Mức độ công việc'), 'reason' => __('Lý do')]);
        $date = Carbon::parse($data['work_date'])->toDateString();
        $hours = $this->overtime->hours($date, $data['start_time'], $data['end_time'], $user);

        $ot = OvertimeRequest::create(['user_id' => $user->id, 'work_date' => $date, 'start_time' => $data['start_time'], 'end_time' => $data['end_time'],
            'hours' => $hours, 'level' => $data['level'], 'reason' => $data['reason'], 'status' => 'pending']);
        foreach ($this->overtime->approversFor($user) as $u) {
            CrmNotification::send($u, 'overtime_request', __(':name đề nghị tăng ca ngày :d (:from–:to, :h giờ).', ['name' => $user->name, 'd' => vn_date($ot->work_date),
                'from' => $data['start_time'], 'to' => $data['end_time'], 'h' => (float) $hours]), route('overtime.index', ['tab' => 'approve']), 'bi-moon-stars');
        }

        return redirect()->route('overtime.index')->with('success', __('Đã gửi đề nghị tăng ca.'));
    }

    public function approve(Request $request, OvertimeRequest $overtime)
    {
        return $this->decide($request, $overtime, 'approved');
    }

    public function reject(Request $request, OvertimeRequest $overtime)
    {
        return $this->decide($request, $overtime, 'rejected');
    }

    public function cancel(Request $request, OvertimeRequest $overtime)
    {
        abort_unless($overtime->user_id === $request->user()->id, 404);
        abort_unless($overtime->status === 'pending', 422, __('Chỉ hủy được đơn đang chờ duyệt.'));
        $overtime->update(['status' => 'cancelled']);

        return back()->with('success', __('Đã hủy đơn tăng ca.'));
    }

    private function decide(Request $request, OvertimeRequest $ot, string $status)
    {
        abort_unless($this->overtime->canDecide($request->user(), $ot), 403);
        abort_unless($ot->status === 'pending', 422, __('Đơn này đã được xử lý.'));
        $data = $request->validate(['decision_note' => [$status === 'rejected' ? 'required' : 'nullable', 'string', 'max:500']], [], ['decision_note' => __('Ghi chú')]);

        DB::transaction(function () use ($ot, $status, $data, $request) {
            $ot->forceFill([
                'status' => $status, 'approver_id' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $data['decision_note'] ?? null,
                'rate' => $status === 'approved' ? $this->overtime->rateFor($ot->user, $ot->level) : null,   // chốt đơn giá lúc duyệt
            ])->save();
        });
        ActivityLogger::log('updated', $ot, ($status === 'approved' ? 'Duyệt' : 'Từ chối').' tăng ca '.$ot->user?->name.' ngày '.vn_date($ot->work_date));
        CrmNotification::send($ot->user, 'overtime_decision', $status === 'approved'
            ? __('Đơn tăng ca ngày :d đã được duyệt (:h giờ × :rate).', ['d' => vn_date($ot->work_date), 'h' => (float) $ot->hours, 'rate' => money($ot->rate)])
            : __('Đơn tăng ca ngày :d bị từ chối: :note', ['d' => vn_date($ot->work_date), 'note' => $ot->decision_note]), route('overtime.index'), 'bi-moon-stars');

        return back()->with('success', $status === 'approved' ? __('Đã duyệt đơn tăng ca.') : __('Đã từ chối đơn tăng ca.'));
    }
}
