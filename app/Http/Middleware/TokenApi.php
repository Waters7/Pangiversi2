<?php

namespace App\Http\Middleware;

use App\Models\LogApi;
use App\Models\Pengaturan;
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
 * Authorization untuk keperluan lain. Tokennya dibuat super administrator
 * di Administrasi Sistem, atau — bila belum — dari PANGI_API_TOKEN di .env.
 */
class TokenApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $mulai = microtime(true);
        $seharusnya = Pengaturan::tokenApi()['token'];
        $dibawa = $this->tokenDari($request);

        // Gagal tertutup: selama token belum dipasang, API menolak semua
        // permintaan. Kalau dibalik — terbuka saat token kosong — satu baris
        // .env yang terlewat sudah cukup untuk membuka data anggaran.
        if ($seharusnya === '') {
            LogApi::catat($request, LogApi::TERTUTUP, $dibawa, $mulai);

            return $this->tolak(
                'Token API belum dipasang pada server. Hubungi administrator PANGI.',
                503,
            );
        }

        // hash_equals, bukan ===: perbandingan biasa berhenti pada karakter
        // pertama yang berbeda, sehingga lama pemeriksaan membocorkan seberapa
        // banyak awalan token yang sudah tertebak.
        if ($dibawa === null || ! hash_equals($seharusnya, $dibawa)) {
            LogApi::catat($request, LogApi::DITOLAK, $dibawa, $mulai);

            return $this->tolak('Token API tidak dikenali.', 401);
        }

        $balasan = $next($request);

        // Dicatat setelah dijawab supaya lamanya ikut terekam; tokennya
        // hanya disimpan ujung-ujungnya (menu Integrasi Data).
        LogApi::catat($request, LogApi::DITERIMA, $dibawa, $mulai);

        return $balasan;
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
