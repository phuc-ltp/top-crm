<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->when($request->input('filter') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->paginate(20)->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    public function feed(Request $request)
    {
        $items = $request->user()->notifications()->limit(10)->get()->map(fn ($n) => [
            'id' => $n->id,
            'message' => $n->data['message'] ?? '',
            'icon' => $n->data['icon'] ?? 'bi-bell',
            'read' => $n->read_at !== null,
            'time' => $n->created_at->diffForHumans(),
            'open_url' => route('notifications.open', $n->id),
        ]);

        return response()->json(['items' => $items, 'unread' => $request->user()->unreadNotifications()->count()]);
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

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Đã đánh dấu tất cả là đã đọc.']);
        }

        return back()->with('success', 'Đã đánh dấu tất cả là đã đọc.');
    }
}
