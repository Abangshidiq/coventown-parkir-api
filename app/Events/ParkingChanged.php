<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ParkingChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * Saluran publik "parking". Isinya hanya sinyal, tanpa data pribadi apa pun.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('parking')];
    }

    /**
     * Nama event yang didengar frontend.
     */
    public function broadcastAs(): string
    {
        return 'parking.changed';
    }

    /**
     * Isi pesan: hanya waktu perubahan. Frontend lalu mengambil data terbaru lewat API.
     *
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['changed_at' => now()->toIso8601String()];
    }

    /**
     * Kirim sinyal dengan aman: kalau server real-time mati, API tetap berhasil
     * dan kesalahannya hanya dicatat di log.
     */
    public static function notify(): void
    {
        try {
            static::dispatch();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}