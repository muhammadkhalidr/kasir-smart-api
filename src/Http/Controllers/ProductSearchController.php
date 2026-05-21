<?php

namespace Khalid\KasirSmartApi\Http\Controllers;

use Illuminate\Routing\Controller;
use Khalid\KasirSmartApi\Traits\ApiResponseTrait;
use Khalid\KasirSmartApi\Services\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ProductSearchService $searchService) {}

    public function search(Request $request): JsonResponse
    {
        $q = $request->input('q', '');
        if (mb_strlen(trim($q)) < 2) {
            return response()->json(['success' => false, 'message' => 'Parameter q minimal 2 karakter.'], 422);
        }

        try {
            $result = $this->searchService->search($q, (int) $request->input('per_page', 10));

            if (empty($result['all_results'])) {
                return response()->json([
                    'success' => false, 'query' => $result['query'],
                    'normalized_query' => $result['normalized_query'],
                    'implied_category' => $result['implied_category'],
                    'message' => 'Produk tidak ditemukan.',
                    'suggestions' => $result['suggestions'],
                    'best_match' => null, 'alternatives' => [], 'all_results' => [], 'total_found' => 0,
                ]);
            }

            return response()->json([
                'success' => true, 'query' => $result['query'],
                'normalized_query' => $result['normalized_query'],
                'implied_category' => $result['implied_category'],
                'message' => "Ditemukan {$result['total_found']} produk.",
                'best_match' => $result['best_match'],
                'alternatives' => $result['alternatives'],
                'all_results' => $result['all_results'],
                'total_found' => $result['total_found'],
                'suggestions' => $result['suggestions'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Server error.'], 500);
        }
    }
}
