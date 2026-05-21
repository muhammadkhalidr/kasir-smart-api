<?php

namespace Khalid\KasirSmartApi\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * ProductSearchService (Package Version)
 * ----------------------------------------
 * Model-agnostic: menggunakan class model dari config kasir-smart-api.models
 * agar bisa dipakai di project kasir manapun.
 */
class ProductSearchService
{
    private string $produkModel;
    private string $kategoriModel;

    public function __construct()
    {
        $this->produkModel   = config('kasir-smart-api.models.produk', \App\Models\Produk::class);
        $this->kategoriModel = config('kasir-smart-api.models.kategori', \App\Models\KategoriBahan::class);
    }

    private const SYNONYMS = [
        'baner' => 'banner', 'benner' => 'banner', 'bener' => 'banner', 'banar' => 'banner',
        'mmt' => 'spanduk', 'mmr' => 'spanduk', 'flexi' => 'flexy', 'fleksi' => 'flexy',
        'x benner' => 'x-banner', 'x-benner' => 'x-banner', 'xbanner' => 'x-banner',
        'y benner' => 'y-banner', 'y-benner' => 'y-banner',
        'roll benner' => 'roll banner', 'rol banner' => 'roll banner', 'rol benner' => 'roll banner',
        'spandok' => 'spanduk', 'spnduk' => 'spanduk', 'spanduck' => 'spanduk',
        'stiker' => 'sticker', 'stikcer' => 'sticker', 'vinil' => 'vinyl', 'vinyal' => 'vinyl',
        'kartunama' => 'kartu nama', 'katu nama' => 'kartu nama', 'business card' => 'kartu nama', 'namecard' => 'kartu nama',
        'idcard' => 'id card', 'id kard' => 'id card', 'id cad' => 'id card', 'pvc card' => 'id card',
        'brosur kecil' => 'brosur a5', 'brosur besar' => 'brosur a4', 'leaflet' => 'brosur', 'flyer' => 'brosur',
        'kartu undangn' => 'undangan', 'undangn' => 'undangan',
        'acrylic' => 'akrilik', 'akrilic' => 'akrilik', 'akrylik' => 'akrilik',
        'plang toko' => 'papan nama', 'plang' => 'papan nama', 'neon sign' => 'neon box',
        'cap' => 'stempel', 'stamp' => 'stempel',
        't-shirt' => 'kaos', 'tshirt' => 'kaos', 'baju' => 'kaos',
        'foto' => 'fhoto', 'photo' => 'fhoto', 'cetak foto' => 'fhoto',
        'umbul' => 'umbul-umbul', 'bendera' => 'umbul-umbul',
        'bon' => 'nota', 'kwitansi' => 'nota', 'invoice' => 'nota', 'faktur' => 'nota',
        'photobooth' => 'backdrop', 'photo booth' => 'backdrop', 'back drop' => 'backdrop',
        'gelas' => 'mug custom', 'tumeler' => 'tumbler', 'tambler' => 'tumbler',
        'cetak dft' => 'cetak dtf', 'sablon dtf' => 'cetak dtf',
    ];

    private const CATEGORY_TRIGGERS = [
        1 => ['spanduk', 'banner', 'mmt', 'flexy', 'backlite', 'backdrop', 'umbul', 'outdoor', 'dtf', 'sticker uv'],
        2 => ['indoor', 'albatros', 'scoutlite', 'kiwalite', 'photo paper', 'laminasi', 'fhoto', 'sticker eco', 'oracal', 'masking sticker', 'pvc composite'],
        3 => ['brosur', 'nota', 'kalender', 'amplop', 'undangan', 'booklet', 'majalah', 'map'],
        4 => ['mug', 'tumbler', 'kaos', 'merchandise', 'goodie bag', 'souvenir', 'pin'],
        5 => ['kartu nama', 'sticker cromo', 'sticker vinyl', 'a3', 'a4', 'f4', 'hvs', 'art paper', 'cutting'],
        6 => ['stempel', 'id card', 'lanyard', 'roll banner', 'x-banner', 'y-banner', 'neon box', 'plakat', 'jasa', 'foamboard', 'stand'],
        7 => ['akrilik', 'acrylic', 'sablon', 'infraboard'],
    ];

    private const STOPWORDS = [
        'yang', 'untuk', 'bisa', 'ada', 'dengan', 'dan', 'atau', 'kak', 'ya',
        'dong', 'gan', 'bos', 'mau', 'tolong', 'bikin', 'buat', 'harga',
        'berapa', 'minta', 'order', 'pesan', 'mesen', 'cetak', 'print',
        'butuh', 'perlu', 'info', 'tanya', 'nanya', 'mo',
    ];

    public function search(string $rawQuery, int $perPage = 10): array
    {
        $normalized   = $this->normalizeQuery($rawQuery);
        $withSynonyms = $this->applySynonyms($normalized);
        $impliedCategoryId = $this->detectCategory($withSynonyms);
        $candidates = $this->fetchCandidates($withSynonyms, $normalized, $impliedCategoryId);
        $scored = $this->scoreAndRank($candidates, $withSynonyms, $normalized, $impliedCategoryId);
        $topResults = $scored->take($perPage);
        $bestMatch  = $topResults->first();
        $alternatives = $topResults->slice(1)->values();
        $suggestions = [];
        if ($topResults->isEmpty() || ($bestMatch && $bestMatch['similarity_score'] < 35)) {
            $suggestions = $this->buildFuzzySuggestions($withSynonyms);
        }
        $categoryName = $impliedCategoryId ? $this->getCategoryName($impliedCategoryId) : null;

        return [
            'query'            => $rawQuery,
            'normalized_query' => $withSynonyms !== $normalized ? $withSynonyms : $normalized,
            'implied_category' => $categoryName,
            'best_match'       => $bestMatch ? $this->formatBestMatch($bestMatch) : null,
            'alternatives'     => $alternatives->map(fn($p) => $this->formatBestMatch($p))->values()->all(),
            'all_results'      => $topResults->map(fn($p) => $this->formatResult($p))->values()->all(),
            'suggestions'      => $suggestions,
            'total_found'      => $topResults->count(),
        ];
    }

    private function normalizeQuery(string $query): string
    {
        $q = mb_strtolower(trim($query));
        $q = preg_replace('/[^a-z0-9\s\-\/\+]/', ' ', $q);
        foreach (self::STOPWORDS as $word) {
            $q = preg_replace('/\b' . preg_quote($word, '/') . '\b/', ' ', $q);
        }
        return trim(preg_replace('/\s+/', ' ', $q));
    }

    private function applySynonyms(string $query): string
    {
        $synonyms = self::SYNONYMS;
        uksort($synonyms, fn($a, $b) => strlen($b) - strlen($a));
        foreach ($synonyms as $typo => $correct) {
            if (strpos($query, $typo) !== false) {
                $query = str_replace($typo, $correct, $query);
            }
        }
        return trim(preg_replace('/\s+/', ' ', $query));
    }

    private function detectCategory(string $query): ?int
    {
        foreach (self::CATEGORY_TRIGGERS as $categoryId => $triggers) {
            foreach ($triggers as $trigger) {
                if (strpos($query, $trigger) !== false) {
                    return $categoryId;
                }
            }
        }
        return null;
    }

    private function fetchCandidates(string $query, string $raw, ?int $categoryId): Collection
    {
        $model = $this->produkModel;
        $base = $model::with('kategories')->where('status', 'Y')
            ->select('id', 'id_kategori', 'judul', 'harga_jual', 'harga_beli', 'harga_grosir', 'barcode', 'ukuran', 'satuan', 'jumlah');

        $results = collect();

        if ($categoryId) {
            $results = $results->merge((clone $base)->where('id_kategori', $categoryId)->get());
        }

        $tokens = $this->getSearchTokens($query);
        if (!empty($tokens)) {
            $likeProducts = (clone $base)->where(function ($q) use ($query, $tokens) {
                $q->where('judul', 'LIKE', "%{$query}%");
                foreach ($tokens as $token) {
                    $q->orWhere('judul', 'LIKE', "%{$token}%");
                }
            })->limit(50)->get();
            $results = $results->merge($likeProducts);
        }

        if (!empty($tokens)) {
            foreach (array_slice($tokens, 0, 2) as $token) {
                if (strlen($token) >= 4) {
                    $results = $results->merge(
                        (clone $base)->whereRaw('SOUNDEX(judul) = SOUNDEX(?)', [$token])->limit(10)->get()
                    );
                }
            }
        }

        return $results->unique('id');
    }

    private function scoreAndRank(Collection $products, string $query, string $raw, ?int $categoryId): Collection
    {
        return $products->map(function ($product) use ($query, $raw, $categoryId) {
            $score = $this->computeScore($product->judul, $query, $raw, $product->id_kategori, $categoryId);
            $arr = $product->toArray();
            $arr['similarity_score'] = $score;
            $arr['kategori_name']    = $product->kategories?->kategori ?? 'Umum';
            return $arr;
        })->sortByDesc('similarity_score');
    }

    private function computeScore(string $productName, string $query, string $rawQuery, int $productCategoryId, ?int $impliedCategoryId): int
    {
        $pLower = mb_strtolower($productName);
        $qLower = mb_strtolower($query);
        $score  = 0;
        if ($pLower === $qLower) return 100;
        if (strpos($pLower, $qLower) !== false) $score = max($score, 88);
        $tokens = $this->getSearchTokens($qLower);
        if (count($tokens) > 0) {
            $matchCount = count(array_filter($tokens, fn($t) => strpos($pLower, $t) !== false));
            $score = max($score, (int) round(($matchCount / count($tokens)) * 80));
        }
        similar_text($pLower, $qLower, $percent);
        $score = max($score, (int) round($percent * 0.75));
        $productTokens = array_filter(explode(' ', $pLower), fn($t) => strlen($t) >= 3);
        $queryTokens   = $this->getSearchTokens($qLower);
        foreach ($queryTokens as $qt) {
            foreach ($productTokens as $pt) {
                $lev = levenshtein($qt, $pt);
                $maxLen = max(strlen($qt), strlen($pt), 1);
                $score = max($score, (int) round((1 - $lev / $maxLen) * 65));
            }
        }
        if ($impliedCategoryId && $productCategoryId === $impliedCategoryId) {
            $score = min(100, $score + 12);
        }
        return min(100, $score);
    }

    private function buildFuzzySuggestions(string $query): array
    {
        $model = $this->produkModel;
        $products = Cache::remember('kasir_api_all_product_names', 120, function () use ($model) {
            return $model::where('status', 'Y')->select('id', 'judul', 'id_kategori')->get();
        });
        return $products->map(function ($p) use ($query) {
            similar_text(mb_strtolower($p->judul), $query, $percent);
            return ['name' => $p->judul, 'id' => $p->id, 'score' => $percent];
        })->sortByDesc('score')->take(5)->where('score', '>', 25)->pluck('name')->values()->all();
    }

    private function getSearchTokens(string $query): array
    {
        return array_values(array_filter(explode(' ', $query), fn($t) => strlen($t) >= 3));
    }

    private function getCategoryName(?int $id): ?string
    {
        if (!$id) return null;
        $model = $this->kategoriModel;
        return Cache::remember("kasir_api_cat_name_{$id}", 600, fn() => $model::find($id)?->kategori);
    }

    private function formatBestMatch(array $product): array
    {
        return ['id' => $product['id'], 'name' => $product['judul'], 'category_id' => $product['id_kategori'], 'category_name' => $product['kategori_name'] ?? 'Umum', 'price' => $product['harga_jual'], 'unit' => $product['satuan'], 'similarity_score' => $product['similarity_score']];
    }

    private function formatResult(array $product): array
    {
        return ['id' => $product['id'], 'name' => $product['judul'], 'category_id' => $product['id_kategori'], 'category_name' => $product['kategori_name'] ?? 'Umum', 'price' => $product['harga_jual'], 'buy_price' => $product['harga_beli'], 'wholesale_price' => $product['harga_grosir'], 'barcode' => $product['barcode'], 'size_reference' => $product['ukuran'], 'unit' => $product['satuan'], 'stock' => $product['jumlah'], 'price_type' => $this->getPriceType($product['id_kategori']), 'price_formula' => $this->getPriceFormula($product['id_kategori'], $product['harga_jual']), 'similarity_score' => $product['similarity_score']];
    }

    private function getPriceType(int $categoryId): string
    {
        return match(true) { in_array($categoryId, [1, 2]) => 'm2', $categoryId === 7 => 'cm2', default => 'unit' };
    }

    private function getPriceFormula(int $categoryId, int $price): string
    {
        return match(true) {
            in_array($categoryId, [1, 2]) => "Rumus: (Panjang_cm/100) × (Lebar_cm/100) × Jumlah × {$price}. Contoh 10x5m = " . (10 * 5 * $price),
            $categoryId === 7 => "Rumus: Panjang_cm × Lebar_cm × Jumlah × {$price}. Contoh 20×30cm = " . (20 * 30 * $price),
            default => "Rumus: Jumlah × {$price}. Harga flat per unit/pcs.",
        };
    }
}
