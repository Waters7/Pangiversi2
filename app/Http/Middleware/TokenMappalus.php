<?php

namespace App\Http\Middleware;

use App\Models\LogApi;
use App\Services\AksesMappalus;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga API Mappalus dengan token miliknya sendiri, yang hanya diterima
 * lewat header:
 *
 *     Authorization: Bearer <token>
 *
 * Token pada alamat tidak diterima: alamat tercatat pada log peladen dan
 * riwayat peramban. Selama belum ada token, API ini menolak semua permintaan.
 * Seluruh penolakan bermuatan sama, supaya penyerang tidak mengetahui
 * pemeriksaan mana yang gagal; alasannya tercatat pada LogApi.
 */
class TokenMappalus
{
    public function __construct(private AksesMappalus $akses) {}

    public function handle(Request $request, Closure $next): Response
    {
        $mulai = microtime(true);
        $dibawa = $request->bearerToken();

        $penolakan = match (true) {
            ! $this->akses->terbuka() => LogApi::TERTUTUP,
            ! $this->lewatHttps($request) => LogApi::DITOLAK,
            ! $this->akses->alamatDiizinkan((string) $request->ip()) => LogApi::ALAMAT_DITOLAK,
            ! $this->akses->tokenCocok($dibawa) => LogApi::DITOLAK,
            default => null,
        };

        if ($penolakan !== null) {
            LogApi::catat($request, $penolakan, $dibawa, $mulai);

            return $this->tolak();
        }

        $balasan = $next($request);

        LogApi::catat($request, LogApi::DITERIMA, $dibawa, $mulai);

        return $balasan;
    }

    /**
     * Token yang lewat http terbaca di perjalanan. Peladen di balik proksi
     * menerima http biasa, jadi penanda proksinya ikut dibaca; memalsukannya
     * hanya membuka token si pemanggil sendiri.
     */
    private function lewatHttps(Request $request): bool
    {
        return ! app()->isProduction()
            || $request->secure()
            || $request->header('X-Forwarded-Proto') === 'https';
    }

    private function tolak(): JsonResponse
    {
        return response()->json(['message' => 'Akses ditolak.'], 401);
    }
}
