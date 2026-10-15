<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/** Chuẩn phản hồi API: { success, message, data, meta? } */
abstract class ApiController extends Controller
{
    protected function ok($data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    protected function paginated(LengthAwarePaginator $paginator, callable $map): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => collect($paginator->items())->map($map)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
