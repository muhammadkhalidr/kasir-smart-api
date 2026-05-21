<?php

namespace Khalid\KasirSmartApi\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponseTrait
{
    protected function successResponse(mixed $data, string $message = 'Berhasil', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    protected function notFoundResponse(string $message = 'Data tidak ditemukan'): JsonResponse
    {
        return response()->json([
            'status'  => 'not_found',
            'message' => $message,
            'data'    => null,
        ], 404);
    }

    protected function errorResponse(string $message = 'Terjadi kesalahan pada server', int $statusCode = 500): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'data'    => null,
        ], $statusCode);
    }

    protected function paginatedResponse(LengthAwarePaginator $paginator, mixed $data, string $message = 'Berhasil', array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ], $extra), 200);
    }

    protected function validationErrorResponse(mixed $errors, string $message = 'Validasi gagal'): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'data'    => $errors,
        ], 422);
    }
}
