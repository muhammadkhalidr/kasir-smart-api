<?php

namespace Khalid\KasirSmartApi\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Sinonim/keyword per kategori untuk membantu AI memetakan pertanyaan pelanggan.
     */
    private const CATEGORY_META = [
        1 => [
            'description' => 'Produk cetak OUTDOOR (luar ruangan). Cocok untuk spanduk, banner, umbul-umbul, backlite.',
            'keywords'    => ['spanduk', 'banner', 'flexy', 'outdoor', 'backlite', 'umbul', 'korea 440', 'glossy 280', 'doff 340', 'sticker uv', 'backdrop', 'one way vision'],
        ],
        2 => [
            'description' => 'Produk cetak INDOOR (dalam ruangan). Bahan premium: Albatros, Scoutlite, Photo Paper.',
            'keywords'    => ['indoor', 'albatros', 'photo paper', 'duratac', 'scoutlite', 'laminasi', 'foto'],
        ],
        7 => [
            'description' => 'Produk Akrilik & Sablon. Harga dihitung per cm2.',
            'keywords'    => ['akrilik', 'acrylic', 'sablon', 'cetak dtf'],
        ]
    ];

    public function toArray(Request $request): array
    {
        $catId   = (int) $this->id_kategori;
        $catName = $this->kategori_name ?? ($this->kategories?->kategori ?? 'Umum');

        // Deteksi tipe perhitungan harga
        $priceType = match (true) {
            in_array($catId, [1, 2]) => 'm2',
            $catId === 7             => 'cm2',
            default                  => 'unit',
        };

        $priceLabel = match($priceType) {
            'm2'    => 'per meter persegi (m2)',
            'cm2'   => 'per centimeter persegi (cm2)',
            default => 'per unit/pcs',
        };

        return [
            'id'              => $this->id,
            'category_id'     => $catId,
            'category_name'   => $catName,
            'name'            => $this->judul,
            'price'           => (int) $this->harga_jual,
            'price_type'      => $priceType,
            'price_per'       => $priceLabel,
            'price_formula'   => $this->getPriceFormula($priceType, (int) $this->harga_jual),
            'buy_price'       => (int) $this->harga_beli,
            'wholesale_price' => $this->harga_grosir ? (int) $this->harga_grosir : null,
            'barcode'         => $this->barcode,
            'size_reference'  => $this->ukuran,
            'unit'            => $this->satuan,
            'is_custom_size'  => in_array($catId, [1, 2, 7]),
            'stock'           => (int) $this->jumlah,
            'status'          => $this->status === 'Y' ? 'aktif' : 'nonaktif',
            'ai_context'      => $this->getAiContext($catId),
        ];
    }

    private function getPriceFormula(string $type, int $price): string
    {
        return match($type) {
            'm2'  => "Rumus Outdoor/Indoor: (Panjang_cm / 100) * (Lebar_cm / 100) * Jumlah * Harga_Jual. Harga dihitung per meter persegi (m2).",
            'cm2' => "Rumus Akrilik/Sablon: Panjang_cm * Lebar_cm * Jumlah * Harga_Jual. Harga dihitung per centimeter persegi (cm2).",
            default => "Rumus Unit: Jumlah * Harga_Jual. Harga flat per unit/pcs.",
        };
    }

    private function getAiContext(int $catId): array
    {
        $meta = self::CATEGORY_META[$catId] ?? null;
        if (!$meta) return [];

        return [
            'instruction' => $meta['description'],
            'use_for'     => $meta['keywords'],
            'do_not_use_for' => $this->getNegativeKeywords($catId),
        ];
    }

    private function getNegativeKeywords(int $catId): array
    {
        if ($catId === 1) return ['albatros', 'foto', 'photo paper', 'laminasi']; // Outdoor jangan pakai Indoor
        if ($catId === 2) return ['spanduk', 'banner', 'baliho', 'flexy']; // Indoor jangan pakai Outdoor
        return [];
    }
}
