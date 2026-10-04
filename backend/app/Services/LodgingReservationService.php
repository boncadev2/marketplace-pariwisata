<?php

namespace App\Services;

use App\Models\LodgingBooking;
use App\Models\RoomInventory;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LodgingReservationService
{
    /** @param array{room_type_id: int, check_in: string, check_out: string, quantity: int, guests: int} $data */
    public function quote(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $room = RoomType::query()->where('is_active', true)->lockForUpdate()->findOrFail($data['room_type_id']);

            return $this->priceStay($room, $data);
        }, 3);
    }

    /** @param array{room_type_id: int, check_in: string, check_out: string, quantity: int, guests: int} $data */
    public function reserve(User $user, array $data, string $key, string $expectedTotal): LodgingBooking
    {
        return DB::transaction(function () use ($user, $data, $key, $expectedTotal): LodgingBooking {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $existing = LodgingBooking::query()->where('user_id', $user->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless(
                    $existing->room_type_id === (int) $data['room_type_id']
                    && $existing->check_in->toDateString() === $data['check_in']
                    && $existing->check_out->toDateString() === $data['check_out']
                    && $existing->quantity === (int) $data['quantity']
                    && $existing->guests === (int) $data['guests'],
                    409,
                    'Kunci reservasi sudah digunakan untuk rincian berbeda.'
                );

                return $existing;
            }

            $room = RoomType::query()->where('is_active', true)->lockForUpdate()->findOrFail($data['room_type_id']);
            $quote = $this->priceStay($room, $data);
            abort_unless($quote['total_price'] === $expectedTotal, 409, 'Harga berubah. Periksa harga kembali sebelum membuat reservasi.');
            foreach ($quote['nightly_prices'] as $night) {
                $changed = RoomInventory::query()->where('room_type_id', $room->id)
                    ->where('date', $night['date'])->where('stock', '>=', $data['quantity'])
                    ->decrement('stock', $data['quantity']);
                if ($changed !== 1) {
                    throw ValidationException::withMessages(['check_in' => 'Stok kamar berubah. Periksa tanggal kembali.']);
                }
            }

            return LodgingBooking::query()->create([
                ...$data,
                'user_id' => $user->id,
                'idempotency_key' => $key,
                'total_price' => $quote['total_price'],
                'nightly_prices' => $quote['nightly_prices'],
                'status' => 'reserved_sandbox',
            ]);
        }, 3);
    }

    public function cancel(User $user, int $bookingId, bool $paymentRelease = false): LodgingBooking
    {
        $owned = LodgingBooking::query()->where('user_id', $user->id)->findOrFail($bookingId);

        return DB::transaction(function () use ($user, $owned, $paymentRelease): LodgingBooking {
            RoomType::query()->lockForUpdate()->findOrFail($owned->room_type_id);
            $booking = LodgingBooking::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($owned->id);
            if ($booking->status === 'cancelled') {
                return $booking;
            }
            abort_if(! $paymentRelease && $booking->reservationPayment()->exists(), 409, 'Batalkan pembayaran Midtrans melalui halaman pesanan sebelum melepas reservasi.');
            abort_if($booking->completed_at !== null || $booking->checked_in_at !== null, 409, 'Layanan yang sudah dimulai atau selesai tidak dapat dibatalkan.');
            abort_unless($booking->status === 'reserved_sandbox' && $booking->nightly_prices !== null, 409, 'Reservasi ini tidak dapat dibatalkan melalui simulasi.');
            foreach ($booking->nightly_prices as $night) {
                $changed = RoomInventory::query()->where('room_type_id', $booking->room_type_id)
                    ->where('date', $night['date'])->increment('stock', $booking->quantity);
                abort_unless($changed === 1, 409, 'Inventori reservasi tidak lengkap. Hubungi pengelola.');
            }
            $booking->update(['status' => 'cancelled']);

            return $booking;
        }, 3);
    }

    /** @param array{check_in: string, check_out: string, quantity: int, guests: int} $data */
    private function priceStay(RoomType $room, array $data): array
    {
        $start = CarbonImmutable::parse($data['check_in']);
        $end = CarbonImmutable::parse($data['check_out']);
        $nights = (int) $start->diffInDays($end);
        if ($nights < 1 || $nights > 30) {
            throw ValidationException::withMessages(['check_out' => 'Pilih masa inap antara 1 dan 30 malam.']);
        }
        if ($data['guests'] > $room->capacity * $data['quantity']) {
            throw ValidationException::withMessages(['guests' => 'Jumlah tamu melebihi kapasitas kamar.']);
        }
        $inventories = RoomInventory::query()->where('room_type_id', $room->id)
            ->where('date', '>=', $data['check_in'])->where('date', '<', $data['check_out'])
            ->orderBy('date')->lockForUpdate()->get()->keyBy('date');
        $rates = RoomRate::query()->where('room_type_id', $room->id)
            ->where('date', '>=', $data['check_in'])->where('date', '<', $data['check_out'])
            ->orderBy('date')->lockForUpdate()->get()->keyBy('date');
        $total = 0;
        $prices = [];
        for ($date = $start; $date->lt($end); $date = $date->addDay()) {
            $day = $date->toDateString();
            if (! isset($inventories[$day], $rates[$day]) || $inventories[$day]->stock < $data['quantity']) {
                throw ValidationException::withMessages(['check_in' => 'Stok atau tarif belum tersedia untuk seluruh malam.']);
            }
            $price = (string) $rates[$day]->price;
            if (! preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/', $price, $parts)) {
                throw ValidationException::withMessages(['check_in' => 'Tarif kamar tidak valid.']);
            }
            $cents = ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
            if ($cents <= 0) {
                throw ValidationException::withMessages(['check_in' => 'Tarif kamar harus lebih besar dari nol.']);
            }
            $total += $cents * $data['quantity'];
            $prices[] = ['date' => $day, 'price_per_room' => sprintf('%d.%02d', intdiv($cents, 100), $cents % 100)];
        }
        if ($total > 999999999999999) {
            throw ValidationException::withMessages(['quantity' => 'Nilai reservasi melebihi batas yang didukung.']);
        }

        return ['currency' => 'IDR', 'nights' => $nights, 'quantity' => (int) $data['quantity'], 'guests' => (int) $data['guests'], 'total_price' => sprintf('%d.%02d', intdiv($total, 100), $total % 100), 'nightly_prices' => $prices];
    }
}
