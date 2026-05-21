<?php

namespace Khalid\KasirSmartApi\Http\Controllers;

use Illuminate\Routing\Controller;
use Khalid\KasirSmartApi\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerApiController extends Controller
{
    use ApiResponseTrait;

    public function lookup(Request $request): JsonResponse
    {
        $phone = preg_replace('/\D/', '', $request->input('phone', ''));

        if (strlen($phone) < 9) {
            return $this->validationErrorResponse(
                ['phone' => ['Format nomor HP tidak valid. Minimal 9 digit angka.']],
                'Validasi gagal.'
            );
        }

        // Normalisasi format HP
        $variants = array_unique([
            $phone,
            '0' . ltrim($phone, '0'),
            '62' . ltrim($phone, '0'),
            '+62' . ltrim($phone, '0'),
            '0' . substr($phone, 2), // strip 62
        ]);

        $pelangganModel = config('kasir-smart-api.models.pelanggan', \App\Models\Pelanggan::class);

        $customer = $pelangganModel::whereIn('nohp', $variants)->first();

        if (!$customer) {
            return $this->notFoundResponse('Customer dengan nomor HP tersebut tidak ditemukan.');
        }

        return $this->successResponse([
            'name'    => $customer->nama,
            'phone'   => $customer->nohp,
            'email'   => $customer->email,
            'address' => $customer->alamat,
            'city'    => $customer->kota,
            'gender'  => $customer->jk,
        ], 'Customer ditemukan.');
    }
}
