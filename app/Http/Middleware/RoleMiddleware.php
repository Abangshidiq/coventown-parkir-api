<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Izinkan request hanya untuk role yang disebut di route.
     * Contoh pemakaian: ->middleware('role:admin') atau 'role:admin,satpam'
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'Anda tidak punya akses.'], 403);
        }

        return $next($request);
    }
}