<?php

namespace Database\Seeders;

use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Isi data awal: admin pertama dan 18 box parkir.
     */
    public function run(): void
    {
        // Admin pertama: segera ganti PIN setelah login pertama
        $admin = User::firstOrNew(['username' => 'admin']);
        $admin->fill([
            'name'      => 'admin',
            'role'      => 'admin',
            'is_active' => true,
        ]);
        $admin->setPin('482915');
        $admin->save();

        // 18 box parkir: P01 ... P18 (dua digit agar urutannya benar)
        for ($i = 1; $i <= 18; $i++) {
            Slot::firstOrCreate(['code' => sprintf('P%02d', $i)]);
        }
    }
}