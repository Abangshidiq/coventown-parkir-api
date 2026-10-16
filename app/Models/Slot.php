<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slot extends Model
{
    /**
     * Kolom yang boleh diisi massal.
     */
    protected $fillable = [
        'code',
        'status',
    ];

    /**
     * Reservasi yang sedang memegang box ini (paling banyak satu).
     */
    public function reservation()
    {
        return $this->hasOne(Reservation::class);
    }

    /**
     * Riwayat perubahan status box ini.
     */
    public function logs()
    {
        return $this->hasMany(SlotLog::class);
    }

    /**
     * Status yang ditampilkan: 'occupied' (biru), 'booked' (merah), atau 'empty' (abu-abu).
     * 'booked' tidak disimpan di database, tapi dihitung dari ada tidaknya reservasi.
     */
    public function displayStatus(): string
    {
        if ($this->status === 'occupied') {
            return 'occupied';
        }

        return $this->reservation ? 'booked' : 'empty';
    }

    /**
     * Box merah masih terkunci selama jam datang belum tiba.
     */
    public function isLocked(): bool
    {
        return $this->status === 'empty'
            && $this->reservation !== null
            && $this->reservation->arrival_time->isFuture();
    }
}