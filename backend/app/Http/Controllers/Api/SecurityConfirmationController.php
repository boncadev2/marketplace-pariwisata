<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SecurityConfirmationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => ['Kata sandi tidak sesuai.']]);
        }

        $token = Str::random(64);
        Cache::put(
            'sensitive-confirmation:'.hash('sha256', $token),
            $request->user()->id,
            now()->addMinutes(10),
        );

        return response()->json([
            'confirmation_token' => $token,
            'confirmed_until' => now()->addMinutes(10)->toIso8601String(),
        ]);
    }
}
