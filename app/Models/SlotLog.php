<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotLog extends Model
{
    /**
     * Kolom yang boleh diisi massal.
     */
    protected $fillable = [
        'slot_id',
        'user_id',
        'from_status',
        'to_status',
        'note',
    ];

    /**
     * Box yang berubah.
     */
    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }

    /**
     * Petugas yang mengubah (kosong = diubah sistem).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}