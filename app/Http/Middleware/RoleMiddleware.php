<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Expand each role to its group (e.g. 'ppk' → ['ppk','keuangan'])
        $allowed = collect($roles)->flatMap(fn (string $r) => User::expandRole($r))->unique()->all();

        if (! $request->user() || ! in_array($request->user()->role, $allowed)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
