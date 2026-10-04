<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RequireSensitiveConfirmation
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Sensitive-Confirmation');
        $confirmedUserId = is_string($token) && strlen($token) >= 40
            ? (int) Cache::get('sensitive-confirmation:'.hash('sha256', $token), 0)
            : 0;

        if ($confirmedUserId !== (int) $request->user()?->id) {
            return response()->json([
                'message' => 'Konfirmasi kata sandi diperlukan untuk tindakan sensitif ini.',
                'code' => 'SENSITIVE_CONFIRMATION_REQUIRED',
            ], 423);
        }

        return $next($request);
    }
}
