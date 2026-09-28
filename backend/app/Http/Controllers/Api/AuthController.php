<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower($request->input('email'))]);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'min:12', 'confirmed']]);
        $user = User::create($data);
        event(new Registered($user));

        return response()->json(['data' => $user], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', mb_strtolower($data['email']))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Kredensial tidak valid.'], 422);
        }
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['data' => $user]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(status: 204);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:255']]);
        $request->user()->update(['name' => $data['name']]);

        return response()->json(['data' => $request->user()->fresh()])->header('Cache-Control', 'private, no-store');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Jika diperlukan, tautan verifikasi telah dikirim.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => mb_strtolower($data['email'])]);

        return response()->json(['message' => 'Jika akun tersedia, instruksi reset telah dikirim.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email'], 'password' => ['required', 'string', 'min:12', 'confirmed']]);
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->save();
        });

        return $status === Password::PASSWORD_RESET ? response()->json(['message' => 'Kata sandi berhasil diubah.']) : response()->json(['message' => 'Token reset tidak valid atau kedaluwarsa.'], 422);
    }
}
