<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    /**
     * Kolom yang boleh diisi massal.
     */
    protected $fillable = [
        'customer_name',
        'plate',
        'arrival_time',
        'slot_id',
        'created_by',
    ];

    /**
     * Perubahan tipe data otomatis.
     */
    protected function casts(): array
    {
        return [
            'arrival_time' => 'datetime',
        ];
    }

    /**
     * Box yang dipilih sistem (bisa kosong = belum dapat parkir).
     */
    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }

    /**
     * Admin yang menginput reservasi.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}