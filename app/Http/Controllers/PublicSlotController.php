<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use Illuminate\Http\JsonResponse;

class PublicSlotController extends Controller
{
    /**
     * Denah untuk customer: hanya kode box dan warna (status). Tanpa login.
     * Sengaja hanya dua field, sehingga data reservasi tidak pernah ikut terkirim.
     */
    public function index(): JsonResponse
    {
        $slots = Slot::with('reservation')->orderBy('code')->get()
            ->map(fn (Slot $slot) => [
                'code'   => $slot->code,
                'status' => $slot->displayStatus(), // empty, booked, occupied
            ]);

        return response()->json($slots);
    }
}