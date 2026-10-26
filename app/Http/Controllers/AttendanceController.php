<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AttendanceService;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Chấm công (bước 5.2).
 *  - Mọi thành viên: trang Chấm công của tôi (vào ca / tan ca với selfie + GPS), xem công của mình.
 *  - payroll.view_all: bảng công tháng của mọi người, xem ảnh selfie / vị trí.
 *  - payroll.manage: điều chỉnh công (bắt buộc ghi lý do, ghi nhật ký).
 */
class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $month = $this->month($request);
        $records = AttendanceRecord::where('user_id', $user->id)->whereBetween('work_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderByDesc('work_date')->get();
        $today = AttendanceRecord::where('user_id', $user->id)->where('work_date', now()->timezone(crm_timezone())->toDateString())->first();
        $consented = (bool) EmployeeProfile::where('user_id', $user->id)->value('attendance_consent_at');

        return view('attendance.index', [
            'records' => $records, 'today' => $today, 'month' => $month, 'consented' => $consented,
            'totals' => ['worked' => $records->sum('worked_minutes'), 'late' => $records->sum('late_minutes'), 'days' => $records->whereNotNull('check_in_at')->count()],
            'hasLocations' => \App\Models\WorkLocation::where('is_active', true)->exists(),
        ]);
    }

    /** Đồng ý cho phép thu thập ảnh selfie và vị trí khi chấm công (Nghị định 13/2023). */
    public function consent(Request $request)
    {
        $request->validate(['agree' => ['accepted']], [], ['agree' => __('Đồng ý')]);
        EmployeeProfile::updateOrCreate(['user_id' => $request->user()->id], ['attendance_consent_at' => now()]);
        ActivityLogger::log('updated', $request->user(), 'Đồng ý thu thập ảnh selfie và vị trí khi chấm công');

        return back()->with('success', __('Cảm ơn bạn. Bạn có thể chấm công ngay bây giờ.'));
    }

    public function punch(Request $request)
    {
        $user = $request->user();
        abort_unless(EmployeeProfile::where('user_id', $user->id)->whereNotNull('attendance_consent_at')->exists(), 403, __('Bạn cần đồng ý điều khoản chấm công trước.'));
        $data = $request->validate([
            'type' => ['required', Rule::in(['in', 'out'])],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], ['latitude' => __('Vị trí'), 'selfie' => __('Ảnh selfie')]);

        $record = $this->attendance->punch($user, $data['type'], ['lat' => (float) $data['latitude'], 'lng' => (float) $data['longitude'], 'accuracy' => isset($data['accuracy']) ? (float) $data['accuracy'] : null],
            $request->file('selfie'), (string) $request->ip());
        $within = $data['type'] === 'in' ? $record->check_in_within : $record->check_out_within;
        $time = ($data['type'] === 'in' ? $record->check_in_at : $record->check_out_at)->timezone(crm_timezone())->format('H:i');
        $message = $data['type'] === 'in' ? __('Đã chấm công vào lúc :time.', ['time' => $time]) : __('Đã chấm công ra lúc :time.', ['time' => $time]);
        $warning = $within ? null : __('Vị trí của bạn nằm ngoài khu vực văn phòng — bản ghi được đánh dấu để quản lý xem xét.');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'warning' => $warning]);
        }

        return redirect()->route('attendance.index')->with('success', $message)->with('warning', $warning);
    }

    /** Bảng công tháng: mỗi người một dòng, mỗi ngày một ô. */
    public function board(Request $request)
    {
        abort_unless($request->user()->can('payroll.view_all'), 403);
        $month = $this->month($request);
        [$start, $end] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
        $users = User::inOrganization()->orderBy('name')->get(['id', 'name']);
        $records = AttendanceRecord::whereBetween('work_date', [$start, $end])->get()
            ->groupBy('user_id')->map(fn ($g) => $g->keyBy(fn ($r) => $r->work_date->toDateString()));
        $days = collect();
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days->push(['date' => $d->copy(), 'work' => $this->attendance->isWorkDay($d)]);
        }

        $leaves = \App\Models\LeaveRequest::where('status', 'approved')->where('start_date', '<=', $end->toDateString())->where('end_date', '>=', $start->toDateString())->get()->groupBy('user_id');
        $holidays = app(\App\Services\LeaveService::class)->holidays($start->toDateString(), $end->toDateString());

        return view('attendance.board', compact('month', 'users', 'records', 'days', 'leaves', 'holidays'));
    }

    public function show(Request $request, AttendanceRecord $record)
    {
        abort_unless($request->user()->can('payroll.view_all') || $record->user_id === $request->user()->id, 404);
        $record->load(['user:id,name', 'checkInLocation:id,name', 'checkOutLocation:id,name', 'adjuster:id,name']);

        return view('attendance.show', compact('record'));
    }

    /** Ảnh selfie: chỉ người có quyền xem bảng công (hoặc chính chủ). */
    public function selfie(Request $request, AttendanceRecord $record, string $type)
    {
        abort_unless(in_array($type, ['in', 'out'], true), 404);
        abort_unless($request->user()->can('payroll.view_all') || $record->user_id === $request->user()->id, 404);
        $path = $type === 'in' ? $record->check_in_selfie : $record->check_out_selfie;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Điều chỉnh công (quên chấm, chấm sai): bắt buộc lý do, ghi nhật ký. */
    public function adjust(Request $request, AttendanceRecord $record)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $data = $request->validate([
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i', 'after:check_in_time'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['reason' => __('Lý do điều chỉnh'), 'check_out_time' => __('Giờ ra')]);
        $this->applyAdjustment($record, $data, $request->user());

        return back()->with('success', __('Đã điều chỉnh công.'));
    }

    /** Thêm công cho ngày chưa có bản ghi (VD: quên chấm cả ngày). */
    public function storeManual(Request $request)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer', \App\Support\Tenancy\TenantRule::member()],
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i', 'after:check_in_time'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['reason' => __('Lý do điều chỉnh')]);
        $record = AttendanceRecord::firstOrNew(['user_id' => $data['user_id'], 'work_date' => Carbon::parse($data['work_date'])->toDateString()]);
        $this->applyAdjustment($record, $data, $request->user());

        return back()->with('success', __('Đã thêm công.'));
    }

    private function applyAdjustment(AttendanceRecord $record, array $data, User $actor): void
    {
        $tz = crm_timezone();
        $date = $record->work_date?->toDateString() ?? $data['work_date'];
        $before = ($record->check_in_at?->timezone($tz)->format('H:i') ?? '—').'–'.($record->check_out_at?->timezone($tz)->format('H:i') ?? '—');
        if (! empty($data['check_in_time'])) {
            $record->check_in_at = Carbon::parse($date.' '.$data['check_in_time'], $tz)->timezone(config('app.timezone'));
        }
        if (! empty($data['check_out_time'])) {
            $record->check_out_at = Carbon::parse($date.' '.$data['check_out_time'], $tz)->timezone(config('app.timezone'));
        }
        $record->forceFill(['adjusted_by' => $actor->id, 'adjusted_at' => now(), 'adjusted_reason' => $data['reason']]);
        $this->attendance->recalculate($record);
        $record->save();
        $after = ($record->check_in_at?->timezone($tz)->format('H:i') ?? '—').'–'.($record->check_out_at?->timezone($tz)->format('H:i') ?? '—');
        ActivityLogger::log('updated', $record, 'Điều chỉnh công '.$record->user?->name.' ngày '.vn_date($record->work_date).": $before → $after. Lý do: ".$data['reason']);
    }

    private function month(Request $request): Carbon
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return Carbon::createFromFormat('Y-m-d', ($request->input('month') ?: now()->format('Y-m')).'-01', crm_timezone())->startOfDay();
    }
}
