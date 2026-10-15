<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Services\ActivityLogger;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

/**
 * Quản lý khóa API cá nhân trong tổ chức đang làm việc.
 * - Tạo khóa: cần quyền "api.use" + nhập lại mật khẩu; khóa hiển thị đúng một lần.
 * - Thu hồi: chủ khóa luôn thu hồi được; quản trị người dùng (users.manage) thu hồi được mọi khóa trong tổ chức.
 */
class ApiTokenController extends Controller
{
    private const TOKEN_FLASH = 'api_token_plain';

    private function orgId(): int
    {
        return (int) app(CurrentOrganization::class)->id();
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $orgId = $this->orgId();
        $canCreate = $user->can('api.use');
        $canManageAll = $user->can('users.manage');

        $plain = null;
        if ($encrypted = $request->session()->get(self::TOKEN_FLASH)) {
            try {
                $plain = Crypt::decrypt($encrypted);
            } catch (\Throwable) {
                $plain = null;
            }
        }

        return view('profile.api-tokens', [
            'tokens' => $user->tokens()->where('organization_id', $orgId)->latest()->get(),
            'orgTokens' => $canManageAll
                ? ApiToken::where('organization_id', $orgId)->where(fn ($q) => $q->where('tokenable_id', '!=', $user->id)->orWhere('tokenable_type', '!=', $user->getMorphClass()))
                    ->with('tokenable')->latest()->get()
                : collect(),
            'canCreate' => $canCreate,
            'canManageAll' => $canManageAll,
            'grantable' => ApiToken::grantableScopes($user),
            'plainToken' => $plain,
            'organization' => app(CurrentOrganization::class)->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('api.use'), 403);
        $user = $request->user();
        $orgId = $this->orgId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(ApiToken::grantableScopes($user))],
            'expires_in' => ['required', 'integer', Rule::in(ApiToken::EXPIRY_DAYS)],
            'password' => ['required', 'string', 'current_password'],
        ], [], ['name' => __('Tên khóa'), 'scopes' => __('Phạm vi truy cập'), 'expires_in' => __('Thời hạn'), 'password' => __('Mật khẩu hiện tại')]);

        if ($user->tokens()->where('organization_id', $orgId)->active()->count() >= ApiToken::MAX_ACTIVE_PER_USER) {
            return back()->withInput($request->except('password'))
                ->with('error', __('Bạn đã có :max khóa còn hiệu lực. Hãy thu hồi khóa không dùng trước khi tạo thêm.', ['max' => ApiToken::MAX_ACTIVE_PER_USER]));
        }

        $new = \Illuminate\Support\Facades\DB::transaction(function () use ($user, $data, $orgId) {
            $new = $user->createToken($data['name'], array_values(array_unique($data['scopes'])), now()->addDays((int) $data['expires_in']));
            $new->accessToken->forceFill(['organization_id' => $orgId])->save();

            return $new;
        });
        ActivityLogger::log('created', $user, 'Tạo khóa API "'.$data['name'].'" (phạm vi: '.implode(', ', $data['scopes']).', hết hạn sau '.$data['expires_in'].' ngày)');

        return redirect()->route('api-tokens.index')
            ->with(self::TOKEN_FLASH, Crypt::encrypt($new->plainTextToken))
            ->with('success', __('Đã tạo khóa API. Hãy sao chép ngay — khóa chỉ hiển thị một lần.'));
    }

    public function destroy(Request $request, int $token)
    {
        $user = $request->user();
        $apiToken = ApiToken::where('organization_id', $this->orgId())->findOrFail($token);
        $isOwner = $apiToken->tokenable_type === $user->getMorphClass() && (int) $apiToken->tokenable_id === (int) $user->id;
        abort_unless($isOwner || $user->can('users.manage'), 403);

        $apiToken->delete();
        ActivityLogger::log('deleted', $user, 'Thu hồi khóa API "'.$apiToken->name.'"'.($isOwner ? '' : ' của '.($apiToken->tokenable?->email ?? '#'.$apiToken->tokenable_id)));

        return back()->with('success', __('Đã thu hồi khóa API. Mọi ứng dụng dùng khóa này sẽ bị từ chối ngay.'));
    }
}
