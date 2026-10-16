<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Slot;
use App\Models\SlotLog;
use Illuminate\Support\Facades\DB;

class SlotAssigner
{
    /**
     * Pilihkan box untuk semua reservasi yang sudah masuk jendela waktu dan belum punya box.
     * Reservasi dengan jam datang paling awal dilayani lebih dulu.
     * Berhenti kalau box kosong habis atau tidak ada lagi reservasi yang menunggu.
     *
     * @return int jumlah reservasi yang baru mendapat box
     */
    public function assignPending(): int
    {
        $assigned = 0;

        // Batas 100 putaran sebagai pengaman agar tidak pernah berputar tanpa henti
        for ($i = 0; $i < 100; $i++) {
            if (! $this->assignNext()) {
                break;
            }

            $assigned++;
        }

        return $assigned;
    }

    /**
     * Pilihkan satu box untuk satu reservasi. Mengembalikan false kalau tidak ada yang bisa dikerjakan.
     */
    private function assignNext(): bool
    {
        $limit = now()->addHours((int) config('parking.assign_hours_before'));

        return DB::transaction(function () use ($limit) {
            // Box kosong yang belum dipegang reservasi, kode terkecil lebih dulu (P01, P02, ...)
            $slot = Slot::where('status', 'empty')
                ->whereDoesntHave('reservation')
                ->orderBy('code')
                ->lockForUpdate()
                ->first();

            if (! $slot) {
                return false;
            }

            // Reservasi yang menunggu dan jamnya sudah masuk jendela, jam datang paling awal dulu
            $reservation = Reservation::whereNull('slot_id')
                ->where('arrival_time', '<=', $limit)
                ->orderBy('arrival_time')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                return false;
            }

            $reservation->update(['slot_id' => $slot->id]);

            SlotLog::create([
                'slot_id'     => $slot->id,
                'user_id'     => null, // dikerjakan sistem
                'from_status' => 'empty',
                'to_status'   => 'booked',
                'note'        => "Dipilih otomatis untuk reservasi {$reservation->customer_name} ({$reservation->plate})",
            ]);

            return true;
        }, 3);
    }
}