<?php

namespace Khalid\KasirSmartApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $validKey = config('kasir-smart-api.api_key');

        if (empty($validKey)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'API tidak dikonfigurasi. Hubungi administrator.',
                'data'    => null,
            ], 503);
        }

        $providedKey = $request->header('X-API-Key');

        if (empty($providedKey) || $providedKey !== $validKey) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akses ditolak. API Key tidak valid atau tidak disertakan.',
                'data'    => null,
            ], 401);
        }

        return $next($request);
    }
}
