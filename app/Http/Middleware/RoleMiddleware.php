<?php

namespace App\Http\Middleware;

use App\Enums\PeranPengguna;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses berdasarkan peran secara langsung.
 *
 * Untuk pembatasan berbasis hak akses, gunakan middleware `can:<kemampuan>`
 * yang bersumber dari App\Enums\Kemampuan — middleware ini hanya dipakai bila
 * sebuah halaman memang terikat pada peran tertentu, bukan pada kemampuannya.
 */
class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $pengguna = $request->user();

        if (! $pengguna) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        // Super administrator memegang seluruh hak akses.
        if ($pengguna->isSuperAdmin()) {
            return $next($request);
        }

        $diizinkan = collect($roles)
            ->map(fn (string $role) => PeranPengguna::dari($role))
            ->contains($pengguna->peran);

        if (! $diizinkan) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
