# Kasir Smart API - AI Powered Pos Recommendation Engine

<p align="center">
  <img src="https://img.shields.io/packagist/v/khalid-r/kasir-smart-api.svg" alt="Latest Version">
  <img src="https://img.shields.io/packagist/dt/khalid-r/kasir-smart-api.svg" alt="Total Downloads">
  <img src="https://img.shields.io/packagist/l/khalid-r/kasir-smart-api.svg" alt="License">
</p>

Kasir Smart API adalah package Laravel yang dirancang khusus untuk Sistem POS Kasir Percetakan. Module ini bertindak sebagai otak "Pencarian Cerdas" dan "AI Recommendation Engine" dari Chat Aggregator untuk berinteraksi dengan pelanggan secara otomatis, natural, akurat, dan toleran terhadap *typo* (kesalahan cetak pelanggan).

## Fitur Unggulan

- **🧠 AI Recommendation Engine**: API Endpoint `/api/v1/ai/recommend` khusus untuk Chatbots/Aggregator. Cukup kirim pesan mentah pelanggan dan package ini langsung mengekstrak:
  - Niat pelanggan (kategori produk)
  - Kalkulasi luas area dan ukuran ($panjang \times lebar \times harga$).
  - Rekomendasi produk terakurat dan spesifik (Mencegah *cross-category mix-up*, seperti mencegah produk albatros masuk ke hasil spanduk).
  - Teks instruksi format matang untuk di konsumsi Model LLM (seperti Gemini atau Groq API).
- **🔎 Fuzzy & Typo-Tolerant Product Search**: Pencarian cerdas khusus produk percetakan. Normalisasi kueri, deteksi jenis (banner vs xbener), dan scoring tinggi untuk produk prioritas.
- **🛡️ Built-in Security**: API Authentication dengan token kustom (`X-API-Key`) dan sistem Rate Limiting bawaan.
- **📊 Logging System**: Middleware logging lengkap untuk merekam perilaku pencarian yang dilakukan AI, demi evaluasi di masa depan.
- **🏗️ Model-Agnostic & Zero Configuration**: Dirancang untuk dapat plug-and-play di *independent project* Kasir manapun yang Anda miliki (contoh: Adreena, Atakata, dsb), dengan cara mengganti Model Binding bawaannya melalui file Config.

---

## 🛠 Instalasi

Anda dapat menginstal package ini via [Composer](https://getcomposer.org/):

```bash
composer require khalid-r/kasir-smart-api
```

## ⚙️ Konfigurasi (Opsional Tapi Penting)
Setelah instalasi, sangat direkomendasikan mempublikasikan konfigurasi file untuk me-*mapping* model aplikasi Anda dengan logic package ini.

```bash
php artisan vendor:publish --tag=kasir-smart-api-config
```

Ini akan menghasilkan file `config/kasir-smart-api.php`. Pastikan model yang digunakan (contoh `App\Models\Produk`) benar adanya dalam direktori sistem host Anda.

Anda juga harus menambahkan variabel lingkungan berikut dalam file `.env` project baru Anda:
```env
EXTERNAL_API_KEY="kunci_rahasia_untuk_chat_aggregator"
API_RATE_LIMIT=60
API_LOG_CHANNEL=daily
```

*Selesai! Package ini akan me-load rute `api/v1/...` ke aplikasi Anda secara otomatis tanpa modifikasi.*

---

## 🚀 Penggunaan API & Endpoints

Server Anda sekarang memiliki **2 endpoint brilian**. Pastikan selalu sertakan akses otentikasi di header:
`X-API-Key : VALUE_DARI_ENV`

### 1. `GET /api/v1/ai/recommend?message={pesan_pelanggan_dari_wa}`
Dikembangkan KHUSUS untuk Chat Aggregator.
**Contoh Request:**
`GET /api/v1/ai/recommend?message=mau bikin spanduk warung ukuran 5x3 gan`

**Contoh Response:**
Akan mengembalikan output cerdas berupa JSON yang berisi rincian intent, meta spesifik Outdoor, Array Data harga Spanduk, contoh kalkulasinya langsung untuk luas `15m2`, dan pesan prompt (*ai_context*) siap suap ke API Groq/Gemini Anda.

### 2. `GET /api/v1/products/search?q={kueri}`
Cocok untuk front-end kasir atau live-search manual, mengeliminasi typo dan mencari kata kunci percetakan implisit.

**Contoh Request:**
`GET /api/v1/products/search?q=benner` *(typo)*

## 📄 Lisensi
The MIT License (MIT).
Dibuat dengan ❤️ oleh **Khalid R** untuk memajukan ekosistem Software POS.
