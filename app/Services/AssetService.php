<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetAuditItem;
use App\Models\AssetCategory;
use App\Models\AssetEvent;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quản lý tài sản (bước 5.5).
 *  - Mã tài sản tự sinh TS-000001 theo tổ chức (khóa dòng khi cấp mã).
 *  - Luân chuyển theo máy trạng thái ACTIONS; mỗi thao tác ghi một AssetEvent bất biến.
 *  - Khấu hao đường thẳng theo tháng: (nguyên giá − giá trị thu hồi) / số tháng; tính đến hôm nay hoặc ngày thanh lý.
 */
class AssetService
{
    /** hành động => [trạng thái được phép, trạng thái mới (null = chọn khi thao tác), cần người nhận] */
    public const ACTIONS = [
        'assign' => [['available'], 'in_use', true],
        'recall' => [['in_use'], 'available', false],
        'borrow' => [['available'], 'borrowed', true],
        'return' => [['borrowed'], 'available', false],
        'maintenance' => [['available', 'in_use', 'broken'], 'maintenance', false],
        'repaired' => [['maintenance'], 'available', false],
        'status' => [['available', 'in_use', 'borrowed', 'maintenance', 'broken', 'lost'], null, false],
        'dispose' => [['available', 'broken', 'lost', 'maintenance'], 'disposed', false],
    ];

    private const EVENT_FOR = ['assign' => 'assigned', 'recall' => 'recalled', 'borrow' => 'borrowed', 'return' => 'returned',
        'maintenance' => 'maintenance', 'repaired' => 'repaired', 'status' => 'status', 'dispose' => 'disposed'];

    /** Nhóm tài sản mặc định khi tổ chức chưa có nhóm nào. */
    public static function defaultCategories(): array
    {
        return [
            ['Máy tính & thiết bị CNTT', 36], ['Thiết bị văn phòng', 36], ['Nội thất', 60],
            ['Phương tiện vận tải', 72], ['Công cụ dụng cụ', 24],
        ];
    }

    public function ensureCategories(): void
    {
        if (AssetCategory::exists()) {
            return;
        }
        foreach (self::defaultCategories() as [$name, $months]) {
            AssetCategory::create(['name' => __($name), 'depreciation_months' => $months]);
        }
    }

    public function create(array $data, User $actor): Asset
    {
        return DB::transaction(function () use ($data, $actor) {
            $asset = new Asset($data + ['code' => $data['code'] ?? null]);
            $asset->code = $data['code'] ?? $this->nextCode();
            $asset->save();
            $this->log($asset, 'created', $actor, ['to_status' => 'available', 'note' => null]);

            return $asset;
        });
    }

    public function nextCode(): string
    {
        $last = Asset::withTrashed()->where('code', 'like', 'TS-%')->lockForUpdate()->pluck('code')
            ->map(fn ($c) => (int) substr($c, 3))->max() ?? 0;

        return 'TS-'.str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
    }

    /** Thực hiện một thao tác luân chuyển. $data: target_user_id, due_back_at, status, condition, cost, note, occurred_at. */
    public function act(Asset $asset, string $action, array $data, User $actor): Asset
    {
        [$allowed, $to, $needsUser] = self::ACTIONS[$action] ?? abort(404);

        return DB::transaction(function () use ($asset, $action, $data, $actor, $allowed, $to, $needsUser) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if (! in_array($asset->status, $allowed, true)) {
                throw ValidationException::withMessages(['action' => __('Không thể thực hiện thao tác này khi tài sản đang ở trạng thái “:s”.', ['s' => label_of(Asset::STATUSES, $asset->status)])]);
            }
            $to ??= $data['status'];
            if ($action === 'status' && (! in_array($to, ['available', 'broken', 'lost'], true) || $to === $asset->status)) {
                throw ValidationException::withMessages(['status' => __('Tình trạng mới không hợp lệ.')]);
            }
            $from = $asset->status;
            $target = $needsUser ? (int) $data['target_user_id'] : ($action === 'recall' || $action === 'return' ? $asset->holder_id : null);

            $asset->status = $to;
            $asset->holder_id = $needsUser ? $target : (in_array($to, ['in_use', 'borrowed'], true) ? $asset->holder_id : null);
            $asset->due_back_at = $action === 'borrow' ? $data['due_back_at'] : ($to === 'borrowed' ? $asset->due_back_at : null);
            if ($action === 'dispose') {
                $asset->disposed_at = $data['occurred_at'] ?? now();
                $asset->disposal_value = $data['cost'] ?? 0;
            }
            $asset->save();

            $this->log($asset, self::EVENT_FOR[$action], $actor, [
                'target_user_id' => $target, 'from_status' => $from, 'to_status' => $to,
                'cost' => in_array($action, ['repaired', 'maintenance', 'dispose'], true) ? ($data['cost'] ?? null) : null,
                'condition' => $data['condition'] ?? null, 'note' => $data['note'] ?? null, 'occurred_at' => $data['occurred_at'] ?? null,
            ]);

            return $asset;
        });
    }

    public function log(Asset $asset, string $type, ?User $actor, array $extra = []): AssetEvent
    {
        return AssetEvent::create(['asset_id' => $asset->id, 'type' => $type, 'user_id' => $actor?->id] + array_filter($extra, fn ($v) => $v !== null)
            + ['occurred_at' => now()]);
    }

    /** Khấu hao đường thẳng tại ngày $at (mặc định hôm nay; tài sản đã thanh lý dừng ở ngày thanh lý). */
    public function depreciation(Asset $asset, ?Carbon $at = null): array
    {
        $cost = Decimal::of($asset->purchase_cost);
        $months = (int) $asset->depreciation_months;
        if (! $asset->purchase_date || $months <= 0) {
            return ['monthly' => '0', 'months_used' => 0, 'accumulated' => '0', 'book_value' => Decimal::round($cost, 0), 'percent' => 0];
        }
        $at = ($asset->disposed_at ? $asset->disposed_at->copy() : ($at ?? now()))->startOfDay();
        $used = max(0, min($months, (int) $asset->purchase_date->copy()->startOfDay()->diffInMonths($at)));
        $base = Decimal::clamp(Decimal::sub($cost, $asset->salvage_value), '0');
        $monthly = Decimal::div($base, (string) $months);
        $accumulated = $used >= $months ? $base : Decimal::round(Decimal::mul($monthly, (string) $used), 0);

        return [
            'monthly' => Decimal::round($monthly, 0), 'months_used' => $used, 'accumulated' => $accumulated,
            'book_value' => Decimal::round(Decimal::sub($cost, $accumulated), 0), 'percent' => (int) round($used / $months * 100),
        ];
    }

    /** Số liệu trang tổng quan (tài sản chưa thanh lý, trừ khi ghi khác). */
    public function overview(): array
    {
        $assets = Asset::with('category:id,name')->get();
        $active = $assets->where('status', '!=', 'disposed');
        $book = fn ($list) => Decimal::sum($list->map(fn ($a) => $this->depreciation($a)['book_value']));
        $byCategory = $active->groupBy(fn ($a) => $a->category?->name ?? __('Chưa phân nhóm'))
            ->map(fn ($g) => ['count' => $g->count(), 'cost' => Decimal::sum($g->pluck('purchase_cost')), 'book' => $book($g)])
            ->sortByDesc('count');

        return [
            'total' => $active->count(), 'cost' => Decimal::sum($active->pluck('purchase_cost')), 'book' => $book($active),
            'by_status' => collect(Asset::STATUSES)->map(fn ($v, $k) => $assets->where('status', $k)->count()),
            'by_category' => $byCategory,
            'overdue' => $active->filter->isOverdue()->values(),
            'warranty' => $active->filter(fn ($a) => $a->warranty_until && $a->warranty_until->between(today(), today()->addDays(30)))->sortBy('warranty_until')->values(),
            'maintenance_cost' => Decimal::sum(AssetEvent::where('type', 'repaired')->where('occurred_at', '>=', now()->startOfYear())->pluck('cost')),
        ];
    }

    /** Ghi nhận kết quả kiểm kê một tài sản trong đợt đang mở (ghi đè nếu kiểm lại). */
    public function check(AssetAudit $audit, Asset $asset, string $result, ?string $note, User $actor): AssetAuditItem
    {
        abort_unless($audit->isOpen(), 422, __('Đợt kiểm kê đã đóng.'));
        abort_if($asset->isDisposed(), 422, __('Tài sản đã thanh lý, không kiểm kê.'));

        return DB::transaction(function () use ($audit, $asset, $result, $note, $actor) {
            $item = AssetAuditItem::updateOrCreate(['asset_audit_id' => $audit->id, 'asset_id' => $asset->id],
                ['result' => $result, 'note' => $note, 'checked_by' => $actor->id, 'checked_at' => now()]);
            $this->log($asset, 'checked', $actor, ['condition' => $result === 'damaged' ? 'damaged' : ($result === 'found' ? 'good' : null),
                'note' => trim(__('Kiểm kê “:a”: :r', ['a' => $audit->name, 'r' => label_of(AssetAudit::RESULTS, $result)]).' '.($note ?? '')), 'asset_audit_id' => $audit->id]);

            return $item;
        });
    }

    /** Đóng đợt: tài sản chưa kiểm được ghi "Không tìm thấy". Trả về số tài sản thiếu. */
    public function close(AssetAudit $audit, User $actor): int
    {
        abort_unless($audit->isOpen(), 422, __('Đợt kiểm kê đã đóng.'));

        return DB::transaction(function () use ($audit, $actor) {
            $checked = $audit->items()->pluck('asset_id');
            $missing = Asset::where('status', '!=', 'disposed')->whereNotIn('id', $checked)->get();
            foreach ($missing as $asset) {
                AssetAuditItem::create(['asset_audit_id' => $audit->id, 'asset_id' => $asset->id, 'result' => 'missing', 'checked_by' => $actor->id, 'checked_at' => now(),
                    'note' => __('Chưa được kiểm khi đóng đợt')]);
            }
            $audit->forceFill(['status' => 'closed', 'closed_at' => now()])->save();

            return $audit->items()->where('result', 'missing')->count();
        });
    }
}
