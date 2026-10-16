<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\User;
use App\Rules\StrongPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Daftar semua akun: username, PIN, role, aktif/tidak.
     */
    public function index(): JsonResponse
    {
        $users = User::orderBy('role')->orderBy('username')->get()
            ->map(fn (User $u) => $this->userData($u));

        return response()->json($users);
    }

    /**
     * Tambah akun baru (username, PIN, role).
     */
    public function store(Request $request): JsonResponse
    {
        $this->lowercaseUsername($request);

        $data = $request->validate([
            'username' => ['required', 'string', 'alpha_dash:ascii', 'min:3', 'max:20', 'unique:users,username'],
            'pin'      => ['bail', 'required', 'string', 'digits:6', new StrongPin],
            'role'     => ['required', Rule::in(['admin', 'satpam'])],
        ]);

        $user = new User([
            'name'      => $data['username'],
            'username'  => $data['username'],
            'role'      => $data['role'],
            'is_active' => true,
        ]);
        $user->setPin($data['pin']);
        $user->save();

        return response()->json($this->userData($user), 201);
    }

    /**
     * Ubah username, PIN, role, atau aktif/nonaktif (kirim hanya yang diubah).
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->lowercaseUsername($request);

        $data = $request->validate([
            'username'  => ['sometimes', 'string', 'alpha_dash:ascii', 'min:3', 'max:20', Rule::unique('users', 'username')->ignore($user->id)],
            'pin'       => ['bail', 'sometimes', 'string', 'digits:6', new StrongPin],
            'role'      => ['sometimes', Rule::in(['admin', 'satpam'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Admin tidak boleh menurunkan role atau menonaktifkan akunnya sendiri
        if ($user->id === $request->user()->id) {
            $turunRole = isset($data['role']) && $data['role'] !== 'admin';
            $nonaktif  = array_key_exists('is_active', $data) && ! $data['is_active'];

            if ($turunRole || $nonaktif) {
                return response()->json([
                    'message' => 'Anda tidak bisa menurunkan role atau menonaktifkan akun sendiri.',
                ], 422);
            }
        }

        if (isset($data['username'])) {
            $user->username = $data['username'];
            $user->name     = $data['username'];
        }
        if (isset($data['role'])) {
            $user->role = $data['role'];
        }
        if (array_key_exists('is_active', $data)) {
            $user->is_active = $data['is_active'];
        }
        if (isset($data['pin'])) {
            $user->setPin($data['pin']);
        }

        $user->save();

        // PIN diganti atau akun dinonaktifkan: semua perangkat akun itu otomatis logout
        $nonaktif = array_key_exists('is_active', $data) && ! $data['is_active'];
        if (isset($data['pin']) || $nonaktif) {
            $this->revokeTokens($request, $user);
        }

        return response()->json($this->userData($user));
    }

    /**
     * Hapus akun.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Anda tidak bisa menghapus akun sendiri.'], 422);
        }

        if (Reservation::where('created_by', $user->id)->exists()) {
            return response()->json([
                'message' => 'Akun ini masih punya reservasi aktif. Nonaktifkan saja akunnya.',
            ], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Akun dihapus.']);
    }

    /**
     * Username selalu disimpan huruf kecil.
     */
    private function lowercaseUsername(Request $request): void
    {
        if (is_string($request->input('username'))) {
            $request->merge(['username' => strtolower($request->input('username'))]);
        }
    }

    /**
     * Hapus token akun. Kalau akun sendiri, token yang sedang dipakai dipertahankan.
     */
    private function revokeTokens(Request $request, User $user): void
    {
        $tokens = $user->tokens();

        if ($user->id === $request->user()->id) {
            $tokens->where('id', '!=', $request->user()->currentAccessToken()->id);
        }

        $tokens->delete();
    }

    /**
     * Bentuk data akun yang dikirim ke frontend (admin saja yang boleh melihat PIN).
     */
    private function userData(User $user): array
    {
        return [
            'id'        => $user->id,
            'username'  => $user->username,
            'pin'       => $user->getPin(),
            'role'      => $user->role,
            'is_active' => $user->is_active,
        ];
    }
}