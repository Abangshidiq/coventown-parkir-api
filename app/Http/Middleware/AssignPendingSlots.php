<?php

namespace App\Http\Middleware;

use App\Services\SlotAssigner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssignPendingSlots
{
    public function __construct(private SlotAssigner $assigner)
    {
    }

    /**
     * Sebelum denah atau daftar reservasi dikirim, pilihkan dulu box untuk reservasi yang
     * jendela 7 jamnya sudah tiba. Ini pengganti scheduler di hosting tanpa cron tiap menit.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->assigner->assignPendingSafely();

        return $next($request);
    }
}