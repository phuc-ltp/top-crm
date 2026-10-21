<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Chấm công (bước 5.2).
 *  - Thời điểm vào / ra luôn lấy theo GIỜ MÁY CHỦ (không tin giờ của điện thoại).
 *  - Giờ công = phần giao giữa [vào, ra] với ca sáng và ca chiều (mặc định 7:30–11:30, 13:30–17:30) → tối đa 8 giờ / ngày.
 *  - Đi trễ / về sớm = số phút trong giờ chuẩn bị thiếu ở đầu / cuối ngày; trễ trong phạm vi cho phép (late_grace) không tính.
 *  - Vị trí: khoảng cách tới văn phòng gần nhất (công thức Haversine) tính ở máy chủ; ngoài bán kính vẫn ghi nhận nhưng gắn cờ.
 */
class AttendanceService
{
    public const DEFAULTS = [
        'work_morning_start' => '07:30', 'work_morning_end' => '11:30',
        'work_afternoon_start' => '13:30', 'work_afternoon_end' => '17:30',
        'work_ot_start' => '18:00', 'work_ot_end' => '21:00',
        'work_days' => '1,2,3,4,5',            // ISO: 1 = Thứ Hai … 7 = Chủ nhật
        'attendance_late_grace' => '5',        // phút
        'attendance_selfie_days' => '90',      // số ngày lưu ảnh selfie
    ];

    public static function setting(string $key): string
    {
        return (string) (Setting::get($key) ?: self::DEFAULTS[$key]);
    }

    /** Các khung giờ làm chuẩn của ngày $date: [[bắt đầu, kết thúc], ...] (Carbon, giờ địa phương của tổ chức). */
    public function windows(Carbon $date): array
    {
        $at = fn (string $hm) => Carbon::parse($date->toDateString().' '.$hm, crm_timezone());

        return [
            [$at(self::setting('work_morning_start')), $at(self::setting('work_morning_end'))],
            [$at(self::setting('work_afternoon_start')), $at(self::setting('work_afternoon_end'))],
        ];
    }

    public function isWorkDay(Carbon $date): bool
    {
        return in_array((string) $date->isoWeekday(), array_map('trim', explode(',', self::setting('work_days'))), true);
    }

    /** Số phút làm việc chuẩn trong ngày (mặc định 480). */
    public function scheduledMinutes(Carbon $date): int
    {
        return array_sum(array_map(fn ($w) => (int) $w[0]->diffInMinutes($w[1]), $this->windows($date)));
    }

    /** @return array{worked: int, late: int, early: int} */
    public function compute(Carbon $date, ?Carbon $in, ?Carbon $out): array
    {
        if (! $in) {
            return ['worked' => 0, 'late' => 0, 'early' => 0];
        }
        $windows = $this->windows($date);
        $worked = 0;
        $lateRaw = 0;
        $early = 0;
        foreach ($windows as [$start, $end]) {
            if ($out) {
                $from = $in->max($start);
                $to = $out->min($end);
                $worked += $to->gt($from) ? (int) $from->diffInMinutes($to) : 0;
            }
            // Thiếu ở đầu ngày: phần giờ chuẩn trước lúc vào
            $lateRaw += $in->gt($start) ? (int) $start->diffInMinutes($in->min($end)) : 0;
            // Thiếu ở cuối ngày: phần giờ chuẩn sau lúc ra
            if ($out) {
                $early += $out->lt($end) ? (int) $out->max($start)->diffInMinutes($end) : 0;
            }
        }
        $late = $lateRaw > (int) self::setting('attendance_late_grace') ? $lateRaw : 0;

        return ['worked' => $worked, 'late' => $late, 'early' => $early];
    }

    /** Khoảng cách (mét) giữa hai tọa độ — công thức Haversine. */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round(2 * $r * asin(min(1, sqrt($a))));
    }

    /** Văn phòng gần nhất và khoảng cách; null nếu tổ chức chưa khai báo văn phòng. @return array{0: ?WorkLocation, 1: ?int} */
    public function nearest(?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null) {
            return [null, null];
        }
        $best = null;
        $bestDistance = null;
        foreach (WorkLocation::where('is_active', true)->get() as $loc) {
            $d = self::distance($lat, $lng, (float) $loc->latitude, (float) $loc->longitude);
            if ($bestDistance === null || $d < $bestDistance) {
                [$best, $bestDistance] = [$loc, $d];
            }
        }

        return [$best, $bestDistance];
    }

    /**
     * Ghi nhận vào ca ("in") hoặc tan ca ("out") cho chính người dùng, tại thời điểm hiện tại của máy chủ.
     * @param array{lat: ?float, lng: ?float, accuracy: ?float} $geo
     */
    public function punch(User $user, string $type, array $geo, ?UploadedFile $selfie, string $ip): AttendanceRecord
    {
        $now = now()->timezone(crm_timezone());
        $date = $now->copy()->startOfDay();
        [$loc, $distance] = $this->nearest($geo['lat'], $geo['lng']);
        $within = $loc && $distance !== null && $distance <= $loc->radius_m + (int) min($geo['accuracy'] ?? 0, 100);

        return DB::transaction(function () use ($user, $type, $geo, $selfie, $ip, $now, $date, $loc, $distance, $within) {
            $record = AttendanceRecord::lockForUpdate()->firstOrNew(['user_id' => $user->id, 'work_date' => $date->toDateString()]);
            if ($type === 'in' && $record->check_in_at) {
                throw ValidationException::withMessages(['type' => __('Hôm nay bạn đã chấm công vào lúc :time.', ['time' => $record->check_in_at->timezone(crm_timezone())->format('H:i')])]);
            }
            if ($type === 'out' && ! $record->check_in_at) {
                throw ValidationException::withMessages(['type' => __('Bạn chưa chấm công vào hôm nay.')]);
            }
            $p = $type === 'in' ? 'check_in' : 'check_out';
            $record->forceFill([
                $p.'_at' => $now->copy()->timezone(config('app.timezone')),   // Eloquent lưu theo múi giờ ứng dụng
                $p.'_lat' => $geo['lat'], $p.'_lng' => $geo['lng'], $p.'_accuracy' => $geo['accuracy'],
                $p.'_distance' => $distance, $p.'_location_id' => $loc?->id, $p.'_within' => $within,
                $p.'_selfie' => $selfie ? $selfie->storeAs('attendance/'.app(\App\Support\Tenancy\CurrentOrganization::class)->id().'/'.$now->format('Y/m'), Str::uuid().'.'.($selfie->guessExtension() ?: 'jpg'), 'local') : null,
                $p.'_ip' => $ip,
            ]);
            $this->recalculate($record);
            $record->save();

            return $record;
        });
    }

    /** Tính lại giờ công / trễ / sớm / trạng thái của một bản ghi. */
    public function recalculate(AttendanceRecord $r): void
    {
        $tz = crm_timezone();
        $date = Carbon::parse($r->work_date->toDateString(), $tz);
        $c = $this->compute($date, $r->check_in_at?->copy()->timezone($tz), $r->check_out_at?->copy()->timezone($tz));
        $r->worked_minutes = $c['worked'];
        $r->late_minutes = $c['late'];
        $r->early_minutes = $c['early'];
        $r->status = match (true) {
            ! $r->check_in_at => 'absent',
            ! $r->check_out_at => 'incomplete',
            $c['late'] > 0 || $c['early'] > 0 => 'late',
            default => 'present',
        };
    }
}
