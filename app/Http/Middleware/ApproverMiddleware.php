<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi menu validasi kepada PPK sebagai pemberi keputusan, serta peran
 * yang berhak memantau seluruh usulan (pimpinan dan super administrator).
 */
class ApproverMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->bisaMembukaPersetujuan()) {
            abort(403, 'Anda tidak memiliki kewenangan validasi usulan.');
        }

        return $next($request);
    }
}
