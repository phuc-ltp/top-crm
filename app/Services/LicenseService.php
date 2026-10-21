<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\LicenseAssignment;
use App\Models\Organization;
use App\Models\SoftwareLicense;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bản quyền phần mềm (bước 5.6) + nhắc tài sản.
 *  - Gán cho người hoặc thiết bị, không vượt số chỗ (khóa dòng giấy phép khi gán); một người / thiết bị chỉ giữ một chỗ / giấy phép.
 *  - Nhắc hết hạn ở các mốc REMIND_STAGES ngày (mỗi mốc một lần cho hạn hiện tại; đổi hạn → nhắc lại từ đầu).
 *  - Nhắc tài sản mượn quá hạn mỗi ngày một lần cho người mượn và người quản lý tài sản.
 */
class LicenseService
{
    public const REMIND_STAGES = [30, 7, 0];

    public function assign(SoftwareLicense $license, ?int $userId, ?int $assetId, ?string $note, User $actor): LicenseAssignment
    {
        if (! $userId === ! $assetId) {
            throw ValidationException::withMessages(['user_id' => __('Chọn đúng một: người dùng hoặc thiết bị.')]);
        }

        return DB::transaction(function () use ($license, $userId, $assetId, $note, $actor) {
            $license = SoftwareLicense::whereKey($license->id)->lockForUpdate()->firstOrFail();
            if ($license->state === 'expired') {
                throw ValidationException::withMessages(['user_id' => __('Giấy phép đã hết hạn, hãy gia hạn trước khi gán.')]);
            }
            $active = $license->activeAssignments();
            if ((clone $active)->count() >= $license->seats) {
                throw ValidationException::withMessages(['user_id' => __('Đã dùng hết :n chỗ của giấy phép.', ['n' => $license->seats])]);
            }
            if ((clone $active)->where($userId ? 'user_id' : 'asset_id', $userId ?: $assetId)->exists()) {
                throw ValidationException::withMessages(['user_id' => __('Người dùng / thiết bị này đã được gán giấy phép.')]);
            }
            $a = LicenseAssignment::create(['software_license_id' => $license->id, 'user_id' => $userId, 'asset_id' => $assetId,
                'assigned_by' => $actor->id, 'assigned_at' => now(), 'note' => $note]);
            ActivityLogger::log('assigned', $license, 'Gán giấy phép '.$license->name.' cho '.($userId ? User::find($userId)?->name : Asset::find($assetId)?->code));

            return $a;
        });
    }

    public function release(LicenseAssignment $assignment, User $actor): void
    {
        abort_if($assignment->released_at !== null, 422, __('Lần gán này đã được thu hồi.'));
        $assignment->forceFill(['released_at' => now(), 'released_by' => $actor->id])->save();
        ActivityLogger::log('updated', $assignment->license, 'Thu hồi giấy phép '.$assignment->license?->name.' từ '.($assignment->user?->name ?? $assignment->asset?->code));
    }

    /** Chạy cho mọi tổ chức đang hoạt động. */
    public function runReminders(): array
    {
        $total = ['licenses' => 0, 'loans' => 0];
        foreach (Organization::where('status', 'active')->pluck('id') as $orgId) {
            $r = app(CurrentOrganization::class)->run((int) $orgId, fn () => $this->remindCurrentOrganization());
            $total['licenses'] += $r['licenses'];
            $total['loans'] += $r['loans'];
        }

        return $total;
    }

    public function remindCurrentOrganization(): array
    {
        $licenses = 0;
        $managers = fn (string $perm) => User::inOrganization()->get()->filter(fn ($u) => $u->can($perm));
        $licenseManagers = null;

        foreach (SoftwareLicense::whereNotNull('expires_at')->where('expires_at', '<=', today()->addDays(max(self::REMIND_STAGES)))->get() as $l) {
            $stage = collect(self::REMIND_STAGES)->filter(fn ($s) => $l->days_left <= $s)->min();   // mốc gần nhất đã chạm
            if ($stage === null || ($l->reminded_stage !== null && $l->reminded_stage <= $stage)) {
                continue;
            }
            $licenseManagers ??= $managers('licenses.manage');
            $to = $l->owner ? collect([$l->owner]) : $licenseManagers;
            $msg = $l->days_left < 0
                ? __('Giấy phép :n đã hết hạn ngày :d.', ['n' => $l->name, 'd' => vn_date($l->expires_at)])
                : __('Giấy phép :n hết hạn ngày :d (còn :k ngày).', ['n' => $l->name, 'd' => vn_date($l->expires_at), 'k' => $l->days_left]);
            foreach ($to as $u) {
                CrmNotification::send($u, 'license_expiring', $msg, route('licenses.show', $l), 'bi-key');
            }
            $l->forceFill(['reminded_stage' => $stage])->saveQuietly();
            $licenses++;
        }

        $loans = 0;
        $assetManagers = null;
        foreach (Asset::with('holder')->where('status', 'borrowed')->whereDate('due_back_at', '<', today())
            ->where(fn ($q) => $q->whereNull('overdue_reminded_on')->orWhereDate('overdue_reminded_on', '<', today()))->get() as $a) {
            $assetManagers ??= $managers('assets.manage');
            $msg = __('Tài sản :c — :n quá hạn trả từ :d.', ['c' => $a->code, 'n' => $a->name, 'd' => vn_date($a->due_back_at)]);
            foreach ($assetManagers->concat([$a->holder])->filter()->unique('id') as $u) {
                CrmNotification::send($u, 'asset_overdue', $msg, route('assets.show', $a), 'bi-alarm');
            }
            $a->forceFill(['overdue_reminded_on' => today()])->saveQuietly();
            $loans++;
        }

        return ['licenses' => $licenses, 'loans' => $loans];
    }
}
