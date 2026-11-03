<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\QrImage;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Link mời đăng ký tài khoản (quyền tạo người dùng). Link đầy đủ + mã QR chỉ hiện MỘT lần lúc tạo;
 * không mời được vai trò Chủ sở hữu; Quản trị viên chỉ do chủ sở hữu / quản trị nền tảng mời.
 */
class InvitationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('create', User::class);
        $created = session('invite_created');

        return view('invitations.index', [
            'invitations' => Invitation::with('creator:id,name')->withCount('users')->latest()->paginate($this->perPage($request)),
            'roles' => $this->roles($request),
            'created' => $created ? $created + ['qr' => QrImage::dataUri($created['url'], 6)] : null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys($this->roles($request)))],
            'days' => ['required', 'integer', 'between:1,30'],
            'max_uses' => ['required', 'integer', 'between:1,200'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['role' => __('Vai trò'), 'days' => __('Hiệu lực (ngày)'), 'max_uses' => __('Số người tối đa')]);
        $inv = new Invitation(['role' => $data['role'], 'note' => $data['note'] ?? null, 'max_uses' => $data['max_uses'], 'expires_at' => now()->addDays($data['days'])]);
        $token = $inv->issueToken();
        $inv->forceFill(['created_by' => $request->user()->id])->save();   // nhật ký "Tạo mới link mời" do LogsActivity ghi

        // Link chỉ nằm trong flash của phiên người tạo (hiện một lần); CSDL chỉ giữ bản băm
        return redirect()->route('invitations.index')->with('invite_created', ['url' => route('join.show', $token), 'role' => $data['role'],
            'expires' => vn_date($inv->expires_at, true), 'max_uses' => $data['max_uses']]);
    }

    public function destroy(Request $request, Invitation $invitation)
    {
        $this->authorize('create', User::class);
        if (! $invitation->revoked_at) {
            $invitation->forceFill(['revoked_at' => now()])->save();
        }

        return back()->with('success', __('Đã thu hồi link mời — link không dùng được nữa.'));
    }

    private function orgId(): int
    {
        return (int) app(CurrentOrganization::class)->id();
    }

    /** Vai trò được mời: không bao giờ là Chủ sở hữu; Quản trị viên chỉ khi người mời là chủ sở hữu / quản trị nền tảng. */
    private function roles(Request $request): array
    {
        $canInviteAdmin = $request->user()->isPlatformAdmin() || $request->user()->isOrganizationOwner();

        return Role::where('organization_id', $this->orgId())->orderBy('id')->get()
            ->reject(fn ($r) => $r->name === PermissionCatalog::OWNER || ($r->name === 'Organization Administrator' && ! $canInviteAdmin))
            ->mapWithKeys(fn ($r) => [$r->name => __($r->name)])->all();
    }
}
