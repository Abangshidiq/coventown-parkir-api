<?php

namespace App\Http\Controllers;

use App\Events\ParkingChanged;
use App\Models\Slot;
use App\Models\SlotLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SlotController extends Controller
{
    /**
     * Denah: daftar semua box beserta status, kunci, dan data reservasinya.
     * Untuk admin dan satpam.
     */
    public function index(): JsonResponse
    {
        $slots = Slot::with('reservation')->orderBy('code')->get()
            ->map(fn (Slot $slot) => $this->slotData($slot));

        return response()->json($slots);
    }

    /**
     * Satpam mengubah status box menjadi 'occupied' (terisi) atau 'empty' (kosong).
     */
    public function updateStatus(Request $request, Slot $slot): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['empty', 'occupied'])],
        ]);

        $target = $data['status'];
        $user   = $request->user();

        $result = DB::transaction(function () use ($slot, $target, $user) {
            // Kunci baris box supaya dua klik bersamaan tidak saling menimpa
            $slot = Slot::with('reservation')->lockForUpdate()->findOrFail($slot->id);

            $current     = $slot->displayStatus();
            $reservation = $slot->reservation;

            // Box merah yang jam datangnya belum tiba tidak boleh diubah
            if ($slot->isLocked()) {
                return [
                    'changed'  => false,
                    'response' => response()->json([
                        'message' => 'Box ini dipesan dan terkunci sampai '
                            . $reservation->arrival_time->format('d/m/Y H:i') . '.',
                    ], 409),
                ];
            }

            // Status sudah sama: tidak ada yang diubah
            if ($current === $target) {
                return [
                    'changed'  => false,
                    'response' => response()->json($this->slotData($slot)),
                ];
            }

            $note = null;

            // Box merah yang sudah boleh diubah: reservasi dilepas, datanya dicatat di riwayat
            if ($current === 'booked') {
                $label = "{$reservation->customer_name} ({$reservation->plate})";
                $note  = $target === 'occupied'
                    ? "Reservasi {$label} selesai, box terisi"
                    : "Reservasi {$label} dilepas, box dikosongkan";

                $reservation->delete();
            }

            $slot->update(['status' => $target]);

            SlotLog::create([
                'slot_id'     => $slot->id,
                'user_id'     => $user->id,
                'from_status' => $current,
                'to_status'   => $target,
                'note'        => $note,
            ]);

            $slot->refresh();

            return [
                'changed'  => true,
                'response' => response()->json($this->slotData($slot)),
            ];
        });

        // Sinyal real-time dikirim setelah perubahan benar-benar tersimpan
        if ($result['changed']) {
            ParkingChanged::notify();
        }

        return $result['response'];
    }

    /**
     * Bentuk data box yang dikirim ke frontend.
     */
    private function slotData(Slot $slot): array
    {
        $reservation = $slot->reservation;

        return [
            'id'          => $slot->id,
            'code'        => $slot->code,
            'status'      => $slot->displayStatus(),   // empty, booked, occupied
            'locked'      => $slot->isLocked(),        // true = tampilkan ikon kunci
            'reservation' => $reservation ? [
                'customer_name' => $reservation->customer_name,
                'plate'         => $reservation->plate,
                'arrival_time'  => $reservation->arrival_time->toIso8601String(),
            ] : null,
        ];
    }
}