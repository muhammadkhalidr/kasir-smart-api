<?php

namespace Khalid\KasirSmartApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $start    = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $start) * 1000, 2);

        $channel = config('kasir-smart-api.log_channel', 'api');

        Log::channel($channel)->info('API Request', [
            'method'     => $request->method(),
            'url'        => $request->fullUrl(),
            'api_key'    => substr($request->header('X-API-Key', '-'), 0, 8) . '...',
            'ip'         => $request->ip(),
            'status'     => $response->getStatusCode(),
            'duration_ms'=> $duration,
            'user_agent' => $request->userAgent(),
        ]);

        return $response;
    }
}
