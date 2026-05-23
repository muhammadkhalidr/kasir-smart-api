<?php

namespace Khalid\KasirSmartApi\Http\Controllers;

use Khalid\KasirSmartApi\Http\Resources\ProductResource;
use Illuminate\Routing\Controller;
use Khalid\KasirSmartApi\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductApiController extends Controller
{
    use ApiResponseTrait;

    private const CATEGORY_KEYWORDS = [
        1 => ['keywords' => ['spanduk', 'banner', 'flexy', 'outdoor', 'backlite', 'umbul', 'one way vision', 'korea 440', 'glossy 280', 'doff 340', 'backdrop', 'sticker uv'], 'description' => 'Produk cetak OUTDOOR (luar ruangan). Harga per m2.'],
        2 => ['keywords' => ['indoor', 'photo paper', 'albatros', 'duratac', 'graftac', 'scoutlite', 'kiwalite', 'laminasi', 'fhoto', 'foto', 'sticker eco', 'masking', 'oracal', 'pvc composite'], 'description' => 'Produk cetak INDOOR (dalam ruangan). Harga per m2.'],
        3 => ['keywords' => ['brosur', 'leaflet', 'nota', 'undangan', 'kalender', 'booklet', 'majalah', 'amplop', 'map', 'kartu nama'], 'description' => 'Produk cetak offset/kertas. Harga flat per unit.'],
        4 => ['keywords' => ['merchandise', 'mug', 'tumbler', 'kaos', 'pin', 'goodie bag', 'souvenir'], 'description' => 'Produk merchandise dan souvenir. Harga per unit.'],
        5 => ['keywords' => ['a3', 'a4', 'f4', 'hvs', 'print', 'art paper', 'art cartoon', 'concorde', 'sticker cromo', 'sticker vinyl', 'cutting', 'laminating'], 'description' => 'Produk cetak mesin A3+. Harga per lembar.'],
        6 => ['keywords' => ['stempel', 'id card', 'lanyard', 'roll banner', 'x-banner', 'y-banner', 'neon box', 'foamboard', 'stand', 'plakat', 'jasa', 'backwall'], 'description' => 'Produk custom dan produk jadi. Harga per unit.'],
        7 => ['keywords' => ['akrilik', 'acrylic', 'sablon', 'infraboard', 'cetak dtf'], 'description' => 'Produk akrilik dan sablon. Harga per cm2.'],
    ];

    public function categories(): JsonResponse
    {
        try {
            $kategoriModel = config('kasir-smart-api.models.kategori', \App\Models\KategoriBahan::class);
            $categories = $kategoriModel::where('status', 'Y')
                ->whereHas('produks', fn($q) => $q->where('status', 'Y'))
                ->orderBy('kategori', 'asc')
                ->get(['id', 'kategori']);

            $data = $categories->map(function ($cat) {
                $meta = self::CATEGORY_KEYWORDS[$cat->id] ?? ['keywords' => [], 'description' => 'Produk cetak lainnya.'];
                return [
                    'id'          => $cat->id,
                    'name'        => $cat->kategori,
                    'description' => $meta['description'],
                    'keywords'    => $meta['keywords'],
                    'price_type'  => in_array($cat->id, [1, 2]) ? 'm2' : ($cat->id === 7 ? 'cm2' : 'unit'),
                ];
            });

            return $this->successResponse($data, 'Daftar kategori berhasil diambil. Gunakan keywords untuk memetakan pertanyaan pelanggan ke kategori yang benar.');
        } catch (\Throwable $e) {
            return $this->errorResponse('Gagal mengambil daftar kategori.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search'   => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors(), 'Parameter tidak valid.');
        }

        try {
            $produkModel = config('kasir-smart-api.models.produk', \App\Models\Produk::class);
            $perPage = (int) $request->input('per_page', 15);

            $query = $produkModel::with('kategories')->where('status', 'Y');

            if ($request->filled('search')) {
                $keyword = $request->input('search');
                $query->where(function ($q) use ($keyword) {
                    $q->where('judul', 'LIKE', "%{$keyword}%")
                      ->orWhere('barcode', 'LIKE', "%{$keyword}%")
                      ->orWhere('ukuran', 'LIKE', "%{$keyword}%")
                      ->orWhereHas('kategories', fn($cat) => $cat->where('kategori', 'LIKE', "%{$keyword}%"));
                });
            }

            if ($request->filled('category')) {
                $categoryInput = $request->input('category');
                $query->where(function ($q) use ($categoryInput) {
                    if (is_numeric($categoryInput)) {
                        $q->where('id_kategori', $categoryInput);
                    } else {
                        $q->whereHas('kategories', fn($cat) => $cat->where('kategori', 'LIKE', "%{$categoryInput}%"));
                    }
                });
            }

            $produk = $query->orderBy('judul', 'asc')->paginate($perPage);
            $data = ProductResource::collection($produk->getCollection());
            $dataArray = $data->toArray($request);

            $aiRules = ['ai_rules' => [
                'RULE_1' => 'Hanya rekomendasikan produk yang category_id-nya sesuai dengan jenis produk yang ditanyakan pelanggan.',
                'RULE_2' => 'Untuk SPANDUK/BANNER → HANYA category_id=1 (Outdoor).',
                'RULE_3' => 'Untuk X-BANNER/ROLL BANNER/STEMPEL/ID CARD → HANYA category_id=6 (Custom).',
                'RULE_4' => 'Untuk FOTO/ALBATROS/LAMINASI/SCOUTLITE → HANYA category_id=2 (Indoor).',
                'RULE_5' => 'Untuk AKRILIK/SABLON → HANYA category_id=7 (Akrilik). Harga per cm2.',
                'RULE_6' => 'Gunakan /api/v1/ai/recommend?message={pesan} untuk rekomendasi otomatis yang lebih akurat.',
                'category_map' => ['Outdoor (spanduk)' => 1, 'Indoor (albatros, foto)' => 2, 'Offset (brosur, nota)' => 3, 'Merchandise (mug, kaos)' => 4, 'Mesin A3+ (sticker, kartu nama)' => 5, 'Custom (x-banner, stempel, plakat)' => 6, 'Akrilik (sablon, akrilik)' => 7],
            ]];

            return $this->paginatedResponse($produk, $dataArray, 'Data produk berhasil diambil.', $aiRules);
        } catch (\Throwable $e) {
            Log::error('Kasir Smart API - List Produk Error: ' . $e->getMessage());
            return $this->errorResponse('Terjadi kesalahan saat mengambil data produk.');
        }
    }
}
