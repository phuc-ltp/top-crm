<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\WorkLocation;
use App\Services\ActivityLogger;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Cài đặt chấm công (bước 5.2): giờ làm, khung tăng ca, ngày làm việc, phút cho phép trễ, văn phòng — quyền payroll.manage. */
class AttendanceSettingsController extends Controller
{
    public function edit(Request $request)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $s = collect(array_keys(AttendanceService::DEFAULTS))->mapWithKeys(fn ($k) => [$k => AttendanceService::setting($k)])->all();

        return view('attendance.settings', ['s' => $s, 'locations' => WorkLocation::orderBy('name')->get()]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $time = ['required', 'date_format:H:i'];
        $data = $request->validate([
            'work_morning_start' => $time, 'work_morning_end' => $time, 'work_afternoon_start' => $time, 'work_afternoon_end' => $time,
            'work_ot_start' => $time, 'work_ot_end' => $time,
            'work_days' => ['required', 'array', 'min:1'], 'work_days.*' => ['integer', 'between:1,7'],
            'attendance_late_grace' => ['required', 'integer', 'between:0,60'],
            'attendance_selfie_days' => ['required', 'integer', 'between:7,365'],
        ], [], ['work_days' => __('Ngày làm việc')]);

        $order = [$data['work_morning_start'], $data['work_morning_end'], $data['work_afternoon_start'], $data['work_afternoon_end'], $data['work_ot_start'], $data['work_ot_end']];
        $sorted = $order;
        sort($sorted);
        if ($order !== $sorted || count(array_unique([$data['work_morning_start'], $data['work_morning_end']])) < 2 || $data['work_ot_start'] === $data['work_ot_end']) {
            throw ValidationException::withMessages(['work_morning_start' => __('Các mốc giờ phải tăng dần: vào ca sáng < hết ca sáng ≤ vào ca chiều < hết ca chiều ≤ bắt đầu tăng ca < kết thúc tăng ca.')]);
        }
        $data['work_days'] = implode(',', array_unique(array_map('intval', $data['work_days'])));
        Setting::put(array_map(fn ($v) => (string) $v, $data), 'attendance');
        ActivityLogger::log('updated', null, 'Cập nhật cài đặt chấm công');

        return back()->with('success', __('Đã lưu cài đặt chấm công. Áp dụng cho các lần tính công sau.'));
    }

    public function storeLocation(Request $request)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        WorkLocation::create($this->location($request));

        return back()->with('success', __('Đã thêm văn phòng.'));
    }

    public function updateLocation(Request $request, WorkLocation $location)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $location->update($this->location($request));

        return back()->with('success', __('Đã cập nhật văn phòng.'));
    }

    public function destroyLocation(Request $request, WorkLocation $location)
    {
        abort_unless($request->user()->can('payroll.manage'), 403);
        $location->delete();   // bản ghi công cũ giữ khoảng cách đã tính (khóa ngoại nullOnDelete)

        return back()->with('success', __('Đã xóa văn phòng.'));
    }

    private function location(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'between:30,5000'],
        ], [], ['name' => __('Tên văn phòng'), 'latitude' => __('Vĩ độ'), 'longitude' => __('Kinh độ'), 'radius_m' => __('Bán kính')]);

        return $data + ['is_active' => $request->boolean('is_active', true)];
    }
}
