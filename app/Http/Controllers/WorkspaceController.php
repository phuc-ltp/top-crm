<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Chuyển "không gian làm việc" (tổ chức) của người dùng. */
class WorkspaceController extends Controller
{
    /** Các tổ chức người dùng được phép vào: thành viên đang hoạt động; quản trị nền tảng được vào mọi tổ chức đang hoạt động. */
    public static function availableFor(User $user): Collection
    {
        return $user->isPlatformAdmin()
            ? Organization::where('status', 'active')->orderBy('name')->get(['id', 'name', 'slug'])
            : $user->organizations()->wherePivot('status', 'active')->where('organizations.status', 'active')
                ->orderBy('organizations.name')->get(['organizations.id', 'organizations.name', 'organizations.slug']);
    }

    public function switch(Request $request)
    {
        $data = $request->validate(['organization_id' => ['required', 'integer']]);
        $user = $request->user();
        $target = self::availableFor($user)->firstWhere('id', (int) $data['organization_id']);
        abort_unless($target, 403, __('Bạn không phải thành viên của tổ chức này.'));

        $user->forceFill(['current_organization_id' => $target->id])->saveQuietly();
        ActivityLogger::log('updated', null, 'Chuyển sang tổ chức '.$target->name);

        return redirect()->route('dashboard')->with('success', __('Đã chuyển sang tổ chức :name.', ['name' => $target->name]));
    }
}
