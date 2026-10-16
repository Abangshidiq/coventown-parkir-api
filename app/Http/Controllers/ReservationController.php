<?php

namespace App\Http\Controllers;

use App\Events\ParkingChanged;
use App\Models\Reservation;
use App\Models\Slot;
use App\Models\SlotLog;
use App\Services\SlotAssigner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    public function __construct(private SlotAssigner $assigner)
    {
    }

    /**
     * Daftar reservasi (urut jam datang).
     * Tanpa parameter: semua reservasi yang belum selesai.
     * Dengan ?date=YYYY-MM-DD: hanya reservasi pada tanggal itu.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = Reservation::with(['slot', 'creator'])->orderBy('arrival_time');

        if ($request->filled('date')) {
            $day = Carbon::createFromFormat('Y-m-d', $request->input('date'), config('app.timezone'));
            $query->whereBetween('arrival_time', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
        }

        return response()->json(
            $query->get()->map(fn (Reservation $r) => $this->reservationData($r))
        );
    }

    /**
     * Tambah reservasi: admin hanya mengisi nama, plat, dan jam datang.
     * Box dipilih sistem: langsung kalau jam datang < 7 jam lagi, atau nanti lewat scheduler.
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizePlate($request);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'plate'         => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9 ]+$/'],
            'arrival_time'  => ['required', 'date'],
        ]);

        $arrival = $this->parseArrival($data['arrival_time']);
        $this->ensureFuture($arrival);

        $reservation = Reservation::create([
            'customer_name' => $data['customer_name'],
            'plate'         => $data['plate'],
            'arrival_time'  => $arrival,
            'created_by'    => $request->user()->id,
        ]);

        // Kalau jam datang kurang dari 7 jam lagi, box langsung dicarikan sekarang
        $this->tryAssign();

        ParkingChanged::notify();

        return response()->json(
            $this->reservationData($reservation->refresh()->load(['slot', 'creator'])),
            201
        );
    }

    /**
     * Ubah nama, plat, atau jadwal (kirim hanya yang diubah).
     * Kalau jadwal berubah, box yang sudah dipilih dilepas (kembali abu-abu).
     */
    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        $this->normalizePlate($request);

        $data = $request->validate([
            'customer_name' => ['sometimes', 'string', 'max:100'],
            'plate'         => ['sometimes', 'string', 'max:20', 'regex:/^[A-Z0-9 ]+$/'],
            'arrival_time'  => ['sometimes', 'date'],
        ]);

        $userId = $request->user()->id;

        $result = DB::transaction(function () use ($data, $reservation, $userId) {
            $reservation = $this->lockReservation($reservation);

            if (isset($data['customer_name'])) {
                $reservation->customer_name = $data['customer_name'];
            }
            if (isset($data['plate'])) {
                $reservation->plate = $data['plate'];
            }

            if (isset($data['arrival_time'])) {
                $arrival = $this->parseArrival($data['arrival_time']);

                if (! $arrival->equalTo($reservation->arrival_time)) {
                    $this->ensureFuture($arrival);
                    $reservation->arrival_time = $arrival;

                    // Jadwal berubah: box dilepas, nanti dipilih ulang oleh sistem
                    if ($reservation->slot_id) {
                        SlotLog::create([
                            'slot_id'     => $reservation->slot_id,
                            'user_id'     => $userId,
                            'from_status' => 'booked',
                            'to_status'   => 'empty',
                            'note'        => "Reservasi {$this->label($reservation)} diubah jadwalnya, box dilepas",
                        ]);
                        $reservation->slot_id = null;
                    }
                }
            }

            $reservation->save();

            return $reservation;
        }, 3);

        // Jadwal baru yang sudah masuk jendela 7 jam langsung dicarikan box
        $this->tryAssign();

        ParkingChanged::notify();

        return response()->json($this->reservationData($result->refresh()->load(['slot', 'creator'])));
    }

    /**
     * Batalkan reservasi (hanya admin). Box yang dipegang kembali abu-abu.
     */
    public function destroy(Request $request, Reservation $reservation): JsonResponse
    {
        $userId = $request->user()->id;

        DB::transaction(function () use ($reservation, $userId) {
            $reservation = $this->lockReservation($reservation);

            if ($reservation->slot_id) {
                SlotLog::create([
                    'slot_id'     => $reservation->slot_id,
                    'user_id'     => $userId,
                    'from_status' => 'booked',
                    'to_status'   => 'empty',
                    'note'        => "Reservasi {$this->label($reservation)} dibatalkan admin",
                ]);
            }

            $reservation->delete();
        }, 3);

        ParkingChanged::notify();

        return response()->json(['message' => 'Reservasi dibatalkan.']);
    }

    /**
     * Coba pilihkan box sekarang juga. Kalau gagal, scheduler mencoba lagi dalam semenit.
     */
    private function tryAssign(): void
    {
        try {
            $this->assigner->assignPending();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Kunci baris box (kalau ada) lalu baris reservasi, dengan urutan yang sama dengan satpam
     * (box dulu, reservasi kemudian) agar tidak saling menunggu.
     */
    private function lockReservation(Reservation $bound): Reservation
    {
        if ($bound->slot_id) {
            Slot::whereKey($bound->slot_id)->lockForUpdate()->first();
        }

        $locked = Reservation::lockForUpdate()->findOrFail($bound->id);

        // Datanya baru saja berubah (misalnya box baru dipilih sistem): minta ulangi
        if ($locked->slot_id != $bound->slot_id) {
            abort(409, 'Data reservasi baru saja berubah, silakan coba lagi.');
        }

        return $locked;
    }

    /**
     * Plat selalu huruf besar dengan spasi rapi: "bg  1234 xx" menjadi "BG 1234 XX".
     */
    private function normalizePlate(Request $request): void
    {
        if (is_string($request->input('plate'))) {
            $plate = strtoupper(preg_replace('/\s+/', ' ', trim($request->input('plate'))));
            $request->merge(['plate' => $plate]);
        }
    }

    /**
     * Ubah teks jam datang menjadi waktu zona aplikasi (WIB).
     */
    private function parseArrival(string $value): Carbon
    {
        return Carbon::parse($value, config('app.timezone'))->setTimezone(config('app.timezone'));
    }

    /**
     * Jam datang baru harus di masa depan.
     */
    private function ensureFuture(Carbon $arrival): void
    {
        if ($arrival->lte(now())) {
            throw ValidationException::withMessages([
                'arrival_time' => ['Jam datang harus di masa depan.'],
            ]);
        }
    }

    private function label(Reservation $reservation): string
    {
        return "{$reservation->customer_name} ({$reservation->plate})";
    }

    /**
     * Bentuk data reservasi yang dikirim ke frontend.
     */
    private function reservationData(Reservation $r): array
    {
        return [
            'id'            => $r->id,
            'customer_name' => $r->customer_name,
            'plate'         => $r->plate,
            'arrival_time'  => $r->arrival_time->toIso8601String(),
            'slot_code'     => $r->slot?->code,          // kosong = belum dapat parkir
            'has_parking'   => $r->slot_id !== null,     // true = sudah dapat parkir
            'is_overdue'    => $r->arrival_time->isPast(), // jam datang sudah lewat
            'created_by'    => $r->creator?->username,
        ];
    }
}