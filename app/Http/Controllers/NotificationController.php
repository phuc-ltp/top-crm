<?php

namespace App\Http\Controllers;

use App\Support\NotificationCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Thông báo của chính người dùng: xem theo nhóm, đánh dấu đọc / chưa đọc, xóa, tùy chọn nhận. */
class NotificationController extends Controller
{
    /** Nhóm của một thông báo (thông báo cũ chưa lưu nhóm → suy từ loại). */
    private static function groupOf($n): string
    {
        return $n->data['group'] ?? NotificationCatalog::group($n->data['kind'] ?? null);
    }

    /** Lọc theo nhóm: thông báo cũ không có "group" trong dữ liệu → lọc theo các loại thuộc nhóm. */
    private function scopeGroup($query, ?string $group)
    {
        if (! $group) {
            return $query;
        }
        $kinds = array_keys(array_filter(NotificationCatalog::KINDS, fn ($k) => $k[0] === $group));

        return $query->where(fn ($q) => $q->where('data->group', $group)
            ->orWhere(fn ($w) => $w->whereNull('data->group')->whereIn('data->kind', $kinds ?: ['__none__'])));
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'filter' => ['nullable', Rule::in(['unread'])],
            'group' => ['nullable', Rule::in(array_keys(NotificationCatalog::GROUPS))],
        ]);
        $user = $request->user();
        $notifications = $this->scopeGroup($user->notifications(), $data['group'] ?? null)
            ->when(($data['filter'] ?? null) === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->paginate(20)->withQueryString();

        // Số chưa đọc theo nhóm (cho các nút lọc)
        $unreadByGroup = $user->unreadNotifications()->get(['id', 'data'])->groupBy(fn ($n) => self::groupOf($n))->map->count();
        // Gom theo ngày: Hôm nay / Hôm qua / ngày cụ thể
        $byDay = $notifications->getCollection()->groupBy(function ($n) {
            $d = $n->created_at->copy()->setTimezone(crm_timezone());

            return $d->isToday() ? __('Hôm nay') : ($d->isYesterday() ? __('Hôm qua') : vn_date($d));
        });

        return view('notifications.index', compact('notifications', 'unreadByGroup', 'byDay'));
    }

    public function feed(Request $request)
    {
        $user = $request->user();
        $items = $user->notifications()->limit(10)->get()->map(function ($n) {
            [, $groupIcon, $tone] = NotificationCatalog::GROUPS[self::groupOf($n)];

            return [
                'id' => $n->id,
                'message' => $n->data['message'] ?? '',
                'icon' => $n->data['icon'] ?? $groupIcon,
                'tone' => $tone,
                'read' => $n->read_at !== null,
                'time' => $n->created_at->diffForHumans(),
                'open_url' => route('notifications.open', $n->id),
            ];
        });
        $latest = $user->unreadNotifications()->latest()->first();

        return response()->json([
            'items' => $items,
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $latest ? ['id' => $latest->id, 'message' => $latest->data['message'] ?? ''] : null,
        ]);
    }

    /** Đánh dấu đã đọc rồi chuyển tới đối tượng liên quan (chỉ URL nội bộ). */
    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $path = $notification->data['url'] ?? null;

        // Chỉ chuyển hướng tới đường dẫn nội bộ (chống open redirect)
        return $path && str_starts_with($path, '/') && ! str_starts_with($path, '//')
            ? redirect(url($path)) : redirect()->route('notifications.index');
    }

    /** Đổi trạng thái đọc / chưa đọc của một thông báo. */
    public function toggle(Request $request, string $id)
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->forceFill(['read_at' => $n->read_at ? null : now()])->save();

        return $request->expectsJson() ? response()->json(['success' => true, 'read' => $n->read_at !== null]) : back();
    }

    public function destroy(Request $request, string $id)
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return $request->expectsJson() ? response()->json(['success' => true]) : back()->with('success', __('Đã xóa thông báo.'));
    }

    public function readAll(Request $request)
    {
        $group = $request->validate(['group' => ['nullable', Rule::in(array_keys(NotificationCatalog::GROUPS))]])['group'] ?? null;
        $this->scopeGroup($request->user()->unreadNotifications(), $group)->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => __('Đã đánh dấu tất cả là đã đọc.')]);
        }

        return back()->with('success', __('Đã đánh dấu tất cả là đã đọc.'));
    }

    /** Xóa mọi thông báo đã đọc. */
    public function clearRead(Request $request)
    {
        $count = $request->user()->readNotifications()->delete();

        return back()->with('success', __('Đã xóa :count thông báo đã đọc.', ['count' => $count]));
    }

    public function preferences(Request $request)
    {
        return view('notifications.preferences', ['user' => $request->user(), 'mailEnabled' => NotificationCatalog::mailEnabled()]);
    }

    public function updatePreferences(Request $request)
    {
        $kinds = array_keys(NotificationCatalog::KINDS);
        $request->validate(['app' => ['nullable', 'array'], 'app.*' => ['in:1'], 'mail' => ['nullable', 'array'], 'mail.*' => ['in:1']]);
        $prefs = [];
        foreach ($kinds as $kind) {
            $prefs[$kind] = [
                'app' => NotificationCatalog::mandatory($kind) || $request->has("app.$kind"),
                'mail' => $request->has("mail.$kind"),
            ];
        }
        $request->user()->forceFill(['notification_preferences' => $prefs])->save();

        return back()->with('success', __('Đã lưu tùy chọn thông báo.'));
    }
}
