<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Login dengan username + PIN 6 angka.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'pin'      => ['required', 'string', 'digits:6'],
        ]);

        $user = User::where('username', strtolower($data['username']))->first();

        // Pesan sengaja sama untuk username salah dan PIN salah
        if (! $user || ! Hash::check($data['pin'], $user->password)) {
            return response()->json(['message' => 'Username atau PIN salah.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Akun dinonaktifkan. Hubungi admin.'], 403);
        }

        $tokenName = Str::limit($request->userAgent() ?: 'device', 100, '');
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $this->userData($user),
        ]);
    }

    /**
     * Data akun yang sedang login (untuk cek token dan role).
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userData($request->user()));
    }

    /**
     * Logout: hapus token yang sedang dipakai (perangkat ini saja).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    /**
     * Bentuk data user yang dikirim ke frontend.
     */
    private function userData(User $user): array
    {
        return [
            'id'       => $user->id,
            'username' => $user->username,
            'role'     => $user->role,   // 'admin' atau 'satpam'
        ];
    }
}