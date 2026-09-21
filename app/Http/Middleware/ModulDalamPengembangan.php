<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menahan peran yang modulnya belum tersedia pada halaman pemberitahuan.
 *
 * Pegawai eksternal, mahasiswa, dan outsourcing akan dilayani modul
 * tersendiri yang masih dikembangkan. Sampai modul itu siap, setelah masuk
 * mereka hanya melihat halaman pemberitahuan dan tombol keluar — tidak ada
 * menu lain yang bisa dibuka.
 */
class ModulDalamPengembangan
{
    /** Nama rute yang tetap boleh dibuka oleh peran yang ditahan. */
    private const RUTE_DIBOLEHKAN = ['modul.dalam-pengembangan', 'logout'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if (! $pengguna || $pengguna->peran->modulTersedia()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::RUTE_DIBOLEHKAN, true)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Fitur untuk peran Anda masih dikembangkan.'], 503)
            : redirect()->route('modul.dalam-pengembangan');
    }
}
