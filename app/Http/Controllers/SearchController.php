<?php

namespace App\Http\Controllers;

use App\Models\SearchHistory;
use App\Services\GlobalSearch;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Tìm kiếm toàn cục — chỉ trả về dữ liệu người dùng được phép xem trong tổ chức đang làm việc. */
class SearchController extends Controller
{
    public function __construct(private GlobalSearch $search) {}

    private function orgId(): int
    {
        return (int) app(CurrentOrganization::class)->id();
    }

    /** AJAX: gợi ý khi gõ; ô trống → danh sách tìm gần đây. */
    public function __invoke(Request $request)
    {
        $q = GlobalSearch::normalize($request->input('q'));
        if ($q === '') {
            return response()->json(['groups' => [], 'recent' => $this->search->recent($request->user(), $this->orgId())]);
        }
        $request->merge(['q' => $q])->validate(['q' => ['string', 'min:'.GlobalSearch::MIN_LENGTH, 'max:'.GlobalSearch::MAX_LENGTH]]);

        return response()->json(['groups' => $this->search->suggest($q, $request->user())]);
    }

    /** Trang kết quả đầy đủ: số kết quả theo loại, danh sách có phân trang của loại đang chọn. */
    public function results(Request $request)
    {
        $user = $request->user();
        $allowed = $this->search->allowedTypes($user);
        $q = GlobalSearch::normalize($request->input('q'));
        $request->validate(['type' => ['nullable', Rule::in($allowed)]]);

        if (mb_strlen($q) < GlobalSearch::MIN_LENGTH) {
            return view('search.results', ['q' => $q, 'counts' => [], 'type' => null, 'items' => null, 'types' => GlobalSearch::types(),
                'recent' => $this->search->recent($user, $this->orgId())]);
        }

        $this->search->remember($q, $user, $this->orgId());
        $counts = $this->search->counts($q, $user);
        $type = $request->input('type') ?: (collect($counts)->filter()->keys()->first() ?? ($allowed[0] ?? null));
        $items = $type ? $this->search->query($type, $q, $user)->paginate($this->perPage($request))->withQueryString()
            ->through(fn ($m) => $this->search->present($type, $m)) : null;

        return view('search.results', ['q' => $q, 'counts' => $counts, 'type' => $type, 'items' => $items, 'types' => GlobalSearch::types(),
            'recent' => $this->search->recent($user, $this->orgId())]);
    }

    /** Xóa lịch sử tìm kiếm của chính mình (trong tổ chức đang làm việc). */
    public function clearRecent(Request $request)
    {
        SearchHistory::where('organization_id', $this->orgId())->where('user_id', $request->user()->id)->delete();

        return $request->expectsJson() ? response()->json(['success' => true]) : back()->with('success', __('Đã xóa lịch sử tìm kiếm.'));
    }
}
