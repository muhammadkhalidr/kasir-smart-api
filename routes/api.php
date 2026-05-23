<?php

use Illuminate\Support\Facades\Route;
use Khalid\KasirSmartApi\Http\Controllers\CustomerApiController;
use Khalid\KasirSmartApi\Http\Controllers\ProductApiController;
use Khalid\KasirSmartApi\Http\Controllers\ProductSearchController;
use Khalid\KasirSmartApi\Http\Controllers\AiRecommendController;

// Health Check
Route::get('/health', function () {
    $version = 'unknown';
    if (class_exists(\Composer\InstalledVersions::class)) {
        try {
            $version = \Composer\InstalledVersions::getPrettyVersion('khalid-r/kasir-smart-api');
        } catch (\OutOfBoundsException $e) {
            $version = 'local-dev';
        }
    }

    return response()->json([
        'status'  => 'ok',
        'message' => 'Kasir Smart API aktif dan berjalan.',
        'version' => $version,
        'package' => 'khalid-r/kasir-smart-api',
    ]);
});

// Customer Lookup
Route::get('/customers/lookup', [CustomerApiController::class, 'lookup']);

// Products
Route::get('/products/categories', [ProductApiController::class, 'categories']);
Route::get('/products/search',     [ProductSearchController::class, 'search']);
Route::get('/products',            [ProductApiController::class, 'index']);

// AI Recommendation Engine
Route::get('/ai/recommend', [AiRecommendController::class, 'recommend']);
