<?php

namespace App\Http\Middleware;

use App\Support\ServiceManagementAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceManagementMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(ServiceManagementAccess::admin($request->user()) || ServiceManagementAccess::partnerIds($request->user()) !== [], 403, 'Akses pemilik atau manajer mitra terverifikasi diperlukan.');

        return $next($request);
    }
}
