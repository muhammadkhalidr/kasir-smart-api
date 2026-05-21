<?php

namespace Khalid\KasirSmartApi;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * KasirSmartApiServiceProvider
 * --------------------------------
 * Jembatan utama antara package dan host application.
 * File ini yang otomatis dideteksi Laravel melalui mekanisme auto-discovery.
 *
 * Cara install di project baru:
 *   composer require khalid/kasir-smart-api
 *   php artisan vendor:publish --tag=kasir-smart-api-config
 *   php artisan vendor:publish --tag=kasir-smart-api-migrations
 *   php artisan migrate
 *   # Tambahkan EXTERNAL_API_KEY=xxxx di .env
 */
class KasirSmartApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge konfigurasi default package dengan konfigurasi host app
        $this->mergeConfigFrom(
            __DIR__ . '/../config/kasir-smart-api.php',
            'kasir-smart-api'
        );

        // Daftarkan Service ke container (bisa di-inject via constructor)
        $this->app->singleton(
            \Khalid\KasirSmartApi\Services\ProductSearchService::class
        );
    }

    public function boot(): void
    {
        // --- Publishable Assets ---
        // Developer bisa jalankan: php artisan vendor:publish --tag=kasir-smart-api-config
        $this->publishes([
            __DIR__ . '/../config/kasir-smart-api.php' => config_path('kasir-smart-api.php'),
        ], 'kasir-smart-api-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'kasir-smart-api-migrations');

        // --- Daftarkan Middleware ---
        $router = $this->app['router'];
        $router->aliasMiddleware('kasir.api.key', \Khalid\KasirSmartApi\Http\Middleware\ApiKeyMiddleware::class);
        $router->aliasMiddleware('kasir.api.log', \Khalid\KasirSmartApi\Http\Middleware\ApiLogMiddleware::class);

        // --- Daftarkan Rate Limiter ---
        $this->configureRateLimiting();

        // --- Daftarkan Routes ---
        $this->loadRoutes();

        // --- Daftarkan Log Channel (jika diperlukan) ---
        // Channel 'api' harus ada di config/logging.php host app.
        // Panduan ada di EXTERNAL_API_DOCS.md
    }

    protected function loadRoutes(): void
    {
        $prefix = config('kasir-smart-api.route_prefix', 'v1');

        Route::prefix($prefix)
            ->middleware(['api', 'kasir.api.key', 'throttle:kasir-api', 'kasir.api.log'])
            ->group(__DIR__ . '/../routes/api.php');
    }

    protected function configureRateLimiting(): void
    {
        $limit = (int) config('kasir-smart-api.rate_limit', 60);

        RateLimiter::for('kasir-api', function (Request $request) use ($limit) {
            $identifier = $request->header('X-API-Key', $request->ip());
            return Limit::perMinute($limit)->by($identifier)->response(function () {
                return response()->json([
                    'status'   => 'error',
                    'message'  => 'Terlalu banyak request. Coba lagi dalam 1 menit.',
                    'data'     => null,
                ], 429);
            });
        });
    }
}
