<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Kolom yang boleh diisi massal.
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'pin_encrypted',
        'role',
        'is_active',
    ];

    /**
     * Kolom yang disembunyikan dari respons API.
     */
    protected $hidden = [
        'password',
        'pin_encrypted',
        'remember_token',
    ];

    /**
     * Perubahan tipe data otomatis.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSatpam(): bool
    {
        return $this->role === 'satpam';
    }

    /**
     * Simpan PIN: hash untuk login + versi terenkripsi agar admin bisa melihatnya.
     */
    public function setPin(string $pin): void
    {
        $this->password = $pin;                              // otomatis di-hash
        $this->pin_encrypted = Crypt::encryptString($pin);
    }

    /**
     * Baca PIN asli (untuk ditampilkan ke admin).
     */
    public function getPin(): ?string
    {
        return $this->pin_encrypted ? Crypt::decryptString($this->pin_encrypted) : null;
    }
}