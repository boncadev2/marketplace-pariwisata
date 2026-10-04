<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->platform_role !== 'super_admin' || ! $request->user()->hasVerifiedEmail()) {
            abort(403, 'Akses administrator diperlukan.');
        }

        return $next($request);
    }
}
