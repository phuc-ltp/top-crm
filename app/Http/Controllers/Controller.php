<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /** Số dòng mỗi trang (cho phép 10–100). */
    protected function perPage(Request $request): int
    {
        return min(100, max(10, (int) $request->input('per_page', config('crm.per_page', 15))));
    }

    /** Sắp xếp an toàn theo danh sách cột cho phép (không đưa input thô vào SQL). */
    protected function applySort($query, Request $request, array $allowed, string $default = 'created_at')
    {
        $sort = in_array($request->input('sort'), $allowed, true) ? $request->input('sort') : $default;
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($query->getModel()->qualifyColumn($sort), $direction);
    }

    /** Escape ký tự đặc biệt cho LIKE. */
    protected function like(?string $term): string
    {
        return '%'.addcslashes(trim((string) $term), '%_\\').'%';
    }
}
