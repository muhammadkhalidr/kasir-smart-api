<?php

namespace Khalid\KasirSmartApi\Http\Controllers;

use Illuminate\Routing\Controller;
use Khalid\KasirSmartApi\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * AiRecommendController
 * ----------------------
 * Endpoint KHUSUS untuk Chat Aggregator / AI Chatbot.
 *
 * Endpoint ini menerima langsung PESAN ASLI dari pelanggan, memproses semuanya
 * di sisi server (deteksi produk, kategori, ukuran, dan kalkulasi harga), lalu
 * mengembalikan respons yang sudah siap digunakan AI untuk menjawab pelanggan.
 *
 * Gunakan endpoint ini SEBAGAI GANTI memanggil /products dan membiarkan AI memilih.
 *
 * Endpoint: GET /api/v1/ai/recommend?message={pesan_pelanggan}
 */
class AiRecommendController extends Controller
{
    use ApiResponseTrait;

    private string $produkModel;

    public function __construct()
    {
        $this->produkModel = config('kasir-smart-api.models.produk', \App\Models\Produk::class);
    }

    // ===================================================================
    // PETA PRODUK UNGGULAN PER KATEGORI
    // Diambil dari data SQL production untuk akurasi 100%
    // ===================================================================
    private const FEATURED_PRODUCTS = [
        'spanduk' => [
            'category_id'   => 1,
            'category_name' => 'Outdoor',
            'product_ids'   => [1, 4, 6, 35, 36, 37, 228], // Spanduk Glossy, Korea, Doff, MTR
            'price_type'    => 'm2',
        ],
        'backlite' => [
            'category_id'   => 1,
            'category_name' => 'Outdoor',
            'product_ids'   => [21, 248], // Backlite Neonbox, Backlite UV
            'price_type'    => 'm2',
        ],
        'umbul' => [
            'category_id'   => 1,
            'category_name' => 'Outdoor',
            'product_ids'   => [194], // Umbul-Umbul Kain Silk
            'price_type'    => 'unit',
        ],
        'sticker_outdoor' => [
            'category_id'   => 1,
            'category_name' => 'Outdoor',
            'product_ids'   => [33, 196, 207], // One Way Vision, Cutting Sticker, Sticker UV
            'price_type'    => 'm2',
        ],
        'x_banner' => [
            'category_id'   => 6,
            'category_name' => 'Custom',
            'product_ids'   => [22, 23, 24, 25, 26, 223], // Banner X/Y set & stand
            'price_type'    => 'unit',
        ],
        'roll_banner' => [
            'category_id'   => 6,
            'category_name' => 'Custom',
            'product_ids'   => [27, 28, 29, 30, 31, 164, 208, 211], // Roll Banner set
            'price_type'    => 'unit',
        ],
        'stempel' => [
            'category_id'   => 6,
            'category_name' => 'Custom',
            'product_ids'   => [32, 108, 110, 112, 113, 114, 115, 116, 118, 153, 154, 155, 156, 157, 158],
            'price_type'    => 'unit',
        ],
        'id_card' => [
            'category_id'   => 6,
            'category_name' => 'Custom',
            'product_ids'   => [14, 15, 16, 119, 120], // ID Card 1/2 sisi, Lanyard, Case, Yoyo
            'price_type'    => 'unit',
        ],
        'plakat' => [
            'category_id'   => 6,
            'category_name' => 'Custom',
            'product_ids'   => [249, 250, 251, 252], // Plakat UV berbagai ketebalan + box
            'price_type'    => 'unit',
        ],
        'neon_box' => [
            'category_id'   => 6,
            'category_name' => 'Custom',
            'product_ids'   => [176], // Neon Box
            'price_type'    => 'unit',
        ],
        'albatros' => [
            'category_id'   => 2,
            'category_name' => 'Indoor',
            'product_ids'   => [161, 208], // ALBATROS, Set Roll Banner Albatros
            'price_type'    => 'm2',
        ],
        'indoor' => [
            'category_id'   => 2,
            'category_name' => 'Indoor',
            'product_ids'   => [50, 51, 161, 183, 184, 185, 186, 188, 189, 191, 197, 198, 199, 200, 204],
            'price_type'    => 'm2',
        ],
        'laminasi' => [
            'category_id'   => 2,
            'category_name' => 'Indoor',
            'product_ids'   => [51, 186, 187], // Laminasi Glossy, Doff, MaxDecal
            'price_type'    => 'm2',
        ],
        'foto' => [
            'category_id'   => 2,
            'category_name' => 'Indoor',
            'product_ids'   => [188, 189, 204], // Fhoto Doff, Fhoto Glossy, Photo Paper
            'price_type'    => 'm2',
        ],
        'kartu_nama' => [
            'category_id'   => 5,
            'category_name' => 'Mesin A3+',
            'product_ids'   => [17, 18], // Kartu Nama 1s & 2s
            'price_type'    => 'unit',
        ],
        'brosur' => [
            'category_id'   => 6,
            'category_name' => 'Custom',
            'product_ids'   => [111, 159, 165], // Brosur A5, A4, Rim
            'price_type'    => 'unit',
        ],
        'nota' => [
            'category_id'   => 6,
            'category_name' => 'Custom',
            'product_ids'   => [221, 222, 231, 232, 233, 234, 235, 236, 237, 238, 239, 240, 241, 242, 243, 244, 245],
            'price_type'    => 'unit',
        ],
        'akrilik' => [
            'category_id'   => 7,
            'category_name' => 'Akrilik',
            'product_ids'   => [49, 56, 172, 216], // Akrilik 3mm, Jasa Sablon, Sticker Cutting Oracal, Infraboard
            'price_type'    => 'cm2',
        ],
        'mug' => [
            'category_id'   => 4,
            'category_name' => 'Merchandise',
            'product_ids'   => [19], // MUG CUSTOM
            'price_type'    => 'unit',
        ],
        'kaos' => [
            'category_id'   => 6,
            'category_name' => 'Custom',
            'product_ids'   => [174, 175], // Kaos 24s & 30s
            'price_type'    => 'unit',
        ],
        'undangan' => [
            'category_id'   => 6,
            'category_name' => 'Custom',
            'product_ids'   => [203], // Undangan Custom
            'price_type'    => 'unit',
        ],
    ];

    // ===================================================================
    // SINONIM & KATA KUNCI → KUNCI FEATURED_PRODUCTS
    // ===================================================================
    private const KEYWORD_MAP = [
        // Outdoor / Spanduk
        'spanduk'        => 'spanduk',
        'banner'         => 'spanduk', // Banner outdoor → tunjukkan spanduk
        'mmt'            => 'spanduk',
        'flexy'          => 'spanduk',
        'baner'          => 'spanduk',
        'benner'         => 'spanduk',
        'bener'          => 'spanduk',
        'spandok'        => 'spanduk',
        'backlite'       => 'backlite',
        'umbul'          => 'umbul',
        'one way'        => 'sticker_outdoor',
        'cutting sticker' => 'sticker_outdoor',
        'sticker uv'     => 'sticker_outdoor',

        // X-Banner / Roll Banner
        'x-banner'       => 'x_banner',
        'x banner'       => 'x_banner',
        'y-banner'       => 'x_banner',
        'y banner'       => 'x_banner',
        'xbanner'        => 'x_banner',
        'stand banner'   => 'x_banner',
        'banner stand'   => 'x_banner',
        'dudukannya'     => 'x_banner',
        'dudukan'        => 'x_banner',
        'roll banner'    => 'roll_banner',
        'rollbanner'     => 'roll_banner',
        'rol banner'     => 'roll_banner',

        // Stempel
        'stempel'        => 'stempel',
        'cap'            => 'stempel',

        // ID Card
        'id card'        => 'id_card',
        'idcard'         => 'id_card',
        'lanyard'        => 'id_card',

        // Plakat & Neon
        'plakat'         => 'plakat',
        'neon box'       => 'neon_box',
        'neon'           => 'neon_box',

        // Indoor
        'albatros'       => 'albatros',
        'scoutlite'      => 'indoor',
        'kiwalite'       => 'indoor',
        'indoor'         => 'indoor',
        'photo paper'    => 'foto',
        'foto'           => 'foto',
        'fhoto'          => 'foto',
        'photo'          => 'foto',
        'laminasi'       => 'laminasi',

        // Kertas / A3+
        'kartu nama'     => 'kartu_nama',
        'kartunama'      => 'kartu_nama',
        'brosur'         => 'brosur',
        'leaflet'        => 'brosur',
        'nota'           => 'nota',
        'bon'            => 'nota',
        'invoice'        => 'nota',
        'undangan'       => 'undangan',

        // Akrilik
        'akrilik'        => 'akrilik',
        'acrylic'        => 'akrilik',
        'sablon'         => 'akrilik',

        // Merchandise
        'mug'            => 'mug',
        'gelas'          => 'mug',
        'kaos'           => 'kaos',
        'baju'           => 'kaos',
    ];

    // ===================================================================
    // PUBLIC: Entry Point
    // ===================================================================
    public function recommend(Request $request): JsonResponse
    {
        $message = $request->input('message', '');

        if (mb_strlen(trim($message)) < 3) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter "message" wajib diisi (minimal 3 karakter).',
            ], 422);
        }

        try {
            $msgLower   = mb_strtolower($message);
            $productKey = $this->detectProductKey($msgLower);
            $dimensions = $this->extractDimensions($msgLower);
            $products   = $this->getProducts($productKey);

            if (empty($products)) {
                return response()->json([
                    'success'         => false,
                    'detected_intent' => $productKey,
                    'message'         => 'Produk tidak ditemukan untuk permintaan ini.',
                    'ai_instruction'  => 'Informasikan kepada pelanggan bahwa produk yang diminta saat ini tidak tersedia di katalog. Tawarkan untuk menghubungi admin.',
                    'products'        => [],
                    'price_examples'  => [],
                ], 200);
            }

            $meta         = self::FEATURED_PRODUCTS[$productKey] ?? null;
            $priceType    = $meta['price_type'] ?? 'unit';
            $priceExamples = $this->calculatePriceExamples($products, $priceType, $dimensions);

            return response()->json([
                'success'          => true,
                'customer_message' => $message,
                'detected_intent'  => $productKey ?? 'general',
                'detected_size'    => $dimensions,
                'category'         => $meta ? ['id' => $meta['category_id'], 'name' => $meta['category_name']] : null,
                'price_type'       => $priceType,
                'products'         => $products,
                'price_examples'   => $priceExamples,
                'ai_instruction'   => $this->buildAiInstruction($productKey, $products, $priceType, $dimensions, $priceExamples),
            ], 200);

        } catch (\Throwable $e) {
            Log::error('AI Recommend Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Server error.'], 500);
        }
    }

    // ===================================================================
    // PRIVATE: Deteksi Jenis Produk dari Pesan
    // ===================================================================
    private function detectProductKey(string $message): ?string
    {
        // Urutkan dari frasa terpanjang agar tidak ada false match
        $map = self::KEYWORD_MAP;
        uksort($map, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($map as $keyword => $productKey) {
            if (strpos($message, $keyword) !== false) {
                return $productKey;
            }
        }
        return null;
    }

    // ===================================================================
    // PRIVATE: Ekstrak Dimensi (ukuran) dari Pesan
    // ===================================================================
    private function extractDimensions(string $message): ?array
    {
        // Pola: "3x1", "3x1 meter", "3 x 1", "300x100", "10×5", dll.
        $patterns = [
            '/(\d+(?:[.,]\d+)?)\s*[x×xX]\s*(\d+(?:[.,]\d+)?)\s*(?:m|meter|cm|centimeter)?/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $w = (float) str_replace(',', '.', $matches[1]);
                $h = (float) str_replace(',', '.', $matches[2]);

                // Asumsikan satuan meter jika nilai kecil (< 50), cm jika besar
                $unit = ($w <= 50 && $h <= 50) ? 'meter' : 'cm';

                return [
                    'width'       => $w,
                    'height'      => $h,
                    'unit'        => $unit,
                    'area_m2'     => $unit === 'meter' ? round($w * $h, 2) : round(($w / 100) * ($h / 100), 2),
                    'area_cm2'    => $unit === 'cm' ? round($w * $h, 0) : round($w * 100 * $h * 100, 0),
                    'display'     => "{$w} × {$h} {$unit}",
                ];
            }
        }
        return null;
    }

    // ===================================================================
    // PRIVATE: Ambil Data Produk dari DB
    // ===================================================================
    private function getProducts(?string $productKey): array
    {
        if (!$productKey || !isset(self::FEATURED_PRODUCTS[$productKey])) {
            return [];
        }

        $ids = self::FEATURED_PRODUCTS[$productKey]['product_ids'];
        $model = $this->produkModel;

        return $model::whereIn('id', $ids)
            ->where('status', 'Y')
            ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')')
            ->get(['id', 'judul', 'harga_jual', 'harga_beli', 'harga_grosir', 'ukuran', 'satuan', 'id_kategori'])
            ->map(fn($p) => [
                'id'         => $p->id,
                'name'       => $p->judul,
                'price'      => (int) $p->harga_jual,
                'unit'       => $p->satuan,
                'size_ref'   => $p->ukuran,
            ])
            ->toArray();
    }

    // ===================================================================
    // PRIVATE: Kalkulasi Harga Contoh
    // ===================================================================
    private function calculatePriceExamples(array $products, string $priceType, ?array $dims): array
    {
        if (empty($products)) return [];

        return array_map(function ($p) use ($priceType, $dims) {
            $example = [
                'product_id'    => $p['id'],
                'product_name'  => $p['name'],
                'price_per_unit'=> $p['price'],
                'unit_label'    => match($priceType) {
                    'm2'  => 'per m²',
                    'cm2' => 'per cm²',
                    default => 'per ' . ($p['unit'] ?? 'pcs'),
                },
            ];

            if ($dims && $priceType === 'm2') {
                $total = round($dims['area_m2'] * $p['price']);
                $example['size_input']  = $dims['display'];
                $example['area']        = $dims['area_m2'] . ' m²';
                $example['calculation'] = $dims['area_m2'] . ' m² × Rp ' . number_format($p['price'], 0, ',', '.') . ' = Rp ' . number_format($total, 0, ',', '.');
                $example['total_price'] = $total;
            } elseif ($dims && $priceType === 'cm2') {
                $total = round($dims['area_cm2'] * $p['price']);
                $example['size_input']  = $dims['display'];
                $example['area']        = $dims['area_cm2'] . ' cm²';
                $example['calculation'] = $dims['area_cm2'] . ' cm² × Rp ' . number_format($p['price'], 0, ',', '.') . ' = Rp ' . number_format($total, 0, ',', '.');
                $example['total_price'] = $total;
            } else {
                $example['calculation'] = 'Harga: Rp ' . number_format($p['price'], 0, ',', '.');
            }

            return $example;
        }, $products);
    }

    // ===================================================================
    // PRIVATE: Bangun Instruksi Teks untuk AI
    // ===================================================================
    private function buildAiInstruction(?string $key, array $products, string $priceType, ?array $dims, array $examples): string
    {
        if (empty($products)) {
            return 'Informasikan produk tidak ditemukan dan tawarkan konsultasi dengan admin.';
        }

        $catName = self::FEATURED_PRODUCTS[$key]['category_name'] ?? 'produk';
        $sizeInfo = $dims ? "Ukuran yang diminta: {$dims['display']} (luas: {$dims['area_m2']} m²)." : '';

        $productList = implode(', ', array_column($products, 'name'));
        $priceLabel  = match($priceType) {
            'm2'    => 'per meter persegi (m2)',
            'cm2'   => 'per centimeter persegi (cm2)',
            default => 'per unit/pcs',
        };

        $calcInfo = '';
        if (!empty($examples) && isset($examples[0]['calculation'])) {
            $calcInfo = "Contoh kalkulasi harga:\n";
            foreach (array_slice($examples, 0, 5) as $e) {
                $calcInfo .= "  - {$e['product_name']}: {$e['calculation']}\n";
            }
        }

        return "INSTRUKSI UNTUK AI:\n"
             . "Pelanggan bertanya tentang produk kategori {$catName}. {$sizeInfo}\n\n"
             . "GUNAKAN HANYA produk berikut untuk menjawab (JANGAN tambahkan produk dari kategori lain):\n"
             . "{$productList}\n\n"
             . "Harga dihitung {$priceLabel}.\n\n"
             . "{$calcInfo}\n"
             . "ATURAN KETAT:\n"
             . "- JANGAN menyebut ALBATROS, Cetak Indoor, atau produk Indoor lain untuk pertanyaan {$catName}.\n"
             . "- Sebutkan nama produk asli dari daftar di atas (misal: Spanduk Glossy 280 Gsm, bukan sekadar spanduk).\n"
             . "- Jika ada kalkulasi harga di atas, gunakan angka tersebut langsung. Jangan buat kalkulasi sendiri.\n"
             . "- Berikan pilihan produk sesuai kebutuhan: ekonomis vs premium.";
    }
}
