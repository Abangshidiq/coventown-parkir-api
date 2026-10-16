<?php

namespace App\Console\Commands;

use App\Events\ParkingChanged;
use App\Models\Reservation;
use App\Services\SlotAssigner;
use Illuminate\Console\Command;

class AssignSlots extends Command
{
    /**
     * Nama perintah di terminal.
     */
    protected $signature = 'parking:assign-slots';

    /**
     * Keterangan perintah.
     */
    protected $description = 'Pilihkan box otomatis untuk reservasi yang jam datangnya sudah masuk jendela waktu';

    public function handle(SlotAssigner $assigner): int
    {
        $count = $assigner->assignPending();

        // Reservasi yang jam datangnya baru saja tiba: ikon kunci box perlu hilang di layar
        $unlocked = Reservation::whereNotNull('slot_id')
            ->where('arrival_time', '<=', now())
            ->where('arrival_time', '>', now()->subMinutes(2))
            ->exists();

        if ($count > 0 || $unlocked) {
            ParkingChanged::notify();
        }

        $this->info(
            "Box dipilihkan untuk {$count} reservasi."
            . ($unlocked ? ' Ada kunci box yang baru terbuka.' : '')
        );

        return self::SUCCESS;
    }
}