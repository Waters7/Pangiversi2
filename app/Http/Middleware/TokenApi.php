<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga API PANGI dengan token bersama.
 *
 * Aplikasi pemanggil mengirim tokennya pada header:
 *
 *     Authorization: Bearer <token>
 *
 * Header X-Api-Token juga diterima untuk klien yang sudah memakai header
 * Authorization untuk keperluan lain.
 */
class TokenApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $seharusnya = (string) config('api.token');

        // Gagal tertutup: selama token belum dipasang, API menolak semua
        // permintaan. Kalau dibalik — terbuka saat token kosong — satu baris
        // .env yang terlewat sudah cukup untuk membuka data anggaran.
        if ($seharusnya === '') {
            return $this->tolak(
                'Token API belum dipasang pada server. Hubungi administrator PANGI.',
                503,
            );
        }

        $dibawa = $this->tokenDari($request);

        // hash_equals, bukan ===: perbandingan biasa berhenti pada karakter
        // pertama yang berbeda, sehingga lama pemeriksaan membocorkan seberapa
        // banyak awalan token yang sudah tertebak.
        if ($dibawa === null || ! hash_equals($seharusnya, $dibawa)) {
            return $this->tolak('Token API tidak dikenali.', 401);
        }

        return $next($request);
    }

    private function tokenDari(Request $request): ?string
    {
        return $request->bearerToken() ?? $request->header('X-Api-Token');
    }

    private function tolak(string $pesan, int $kode): JsonResponse
    {
        return response()->json(['message' => $pesan], $kode);
    }
}
