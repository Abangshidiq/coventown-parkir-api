<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jendela pemilihan box otomatis
    |--------------------------------------------------------------------------
    | Berapa jam sebelum jam datang, sistem mulai memilihkan box untuk reservasi.
    */
    'assign_hours_before' => (int) env('PARKING_ASSIGN_HOURS', 7),

];