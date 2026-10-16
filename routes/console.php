<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pilih box otomatis untuk reservasi, dicek setiap menit
Schedule::command('parking:assign-slots')->everyMinute()->withoutOverlapping(5);