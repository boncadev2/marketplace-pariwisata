<?php

namespace App\Services;

use App\Models\MealBooking;
use App\Models\MealSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MealReservationService
{
    public function quote(int $slotId, int $quantity): array
    {
        return DB::transaction(function () use ($slotId, $quantity): array {
            $slot = MealSlot::query()->where('is_active', true)->whereHas('culinaryPlace', fn ($query) => $query->where('is_active', true))->lockForUpdate()->findOrFail($slotId);

            return $this->priceMeal($slot, $quantity);
        }, 3);
    }

    public function reserve(User $user, int $slotId, int $quantity, string $key, string $expectedTotal): MealBooking
    {
        return DB::transaction(function () use ($user, $slotId, $quantity, $key, $expectedTotal): MealBooking {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $existing = MealBooking::query()->where('user_id', $user->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless($existing->meal_slot_id === $slotId && $existing->quantity === $quantity, 409, 'Kunci reservasi sudah digunakan untuk rincian berbeda.');

                return $existing;
            }
            $slot = MealSlot::query()->where('is_active', true)->whereHas('culinaryPlace', fn ($query) => $query->where('is_active', true))->lockForUpdate()->findOrFail($slotId);
            $quote = $this->priceMeal($slot, $quantity);
            abort_unless($quote['total_price'] === $expectedTotal, 409, 'Harga berubah. Periksa harga kembali sebelum membuat reservasi.');
            $changed = MealSlot::query()->whereKey($slotId)->whereRaw('capacity - reserved >= ?', [$quantity])->increment('reserved', $quantity);
            abort_unless($changed === 1, 409, 'Kuota berubah. Periksa slot kembali.');

            return MealBooking::query()->create([
                'user_id' => $user->id, 'meal_slot_id' => $slotId, 'quantity' => $quantity,
                'idempotency_key' => $key, 'unit_price' => $quote['unit_price'], 'total_price' => $quote['total_price'],
                'package_name' => $slot->package_name, 'time_slot' => $slot->time_slot, 'status' => 'reserved_sandbox',
            ]);
        }, 3);
    }

    public function cancel(User $user, int $bookingId, bool $paymentRelease = false): MealBooking
    {
        $owned = MealBooking::query()->where('user_id', $user->id)->findOrFail($bookingId);

        return DB::transaction(function () use ($user, $owned, $paymentRelease): MealBooking {
            MealSlot::query()->lockForUpdate()->findOrFail($owned->meal_slot_id);
            $booking = MealBooking::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($owned->id);
            if ($booking->status === 'cancelled') {
                return $booking;
            }
            abort_if(! $paymentRelease && $booking->reservationPayment()->exists(), 409, 'Batalkan pembayaran Midtrans melalui halaman pesanan sebelum melepas reservasi.');
            abort_if($booking->completed_at !== null, 409, 'Layanan yang sudah dimulai atau selesai tidak dapat dibatalkan.');
            abort_unless($booking->status === 'reserved_sandbox', 409, 'Reservasi ini tidak dapat dibatalkan melalui simulasi.');
            $changed = MealSlot::query()->whereKey($booking->meal_slot_id)->where('reserved', '>=', $booking->quantity)->decrement('reserved', $booking->quantity);
            abort_unless($changed === 1, 409, 'Kuota reservasi tidak konsisten. Hubungi pengelola.');
            $booking->update(['status' => 'cancelled']);

            return $booking;
        }, 3);
    }

    private function priceMeal(MealSlot $slot, int $quantity): array
    {
        if ($quantity < 1 || $quantity > 100) {
            throw ValidationException::withMessages(['quantity' => 'Pilih antara 1 dan 100 peserta.']);
        }
        if ($slot->time_slot->isPast() || $slot->time_slot->equalTo(now())) {
            throw ValidationException::withMessages(['meal_slot_id' => 'Waktu reservasi sudah lewat.']);
        }
        if ($slot->capacity - $slot->reserved < $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Kuota paket makan tidak mencukupi.']);
        }
        if (! preg_match('/^([0-9]+)\.([0-9]{2})$/', $slot->price, $parts)) {
            throw ValidationException::withMessages(['meal_slot_id' => 'Tarif paket makan tidak valid.']);
        }
        $cents = ((int) $parts[1] * 100) + (int) $parts[2];
        $total = $cents * $quantity;
        if ($cents <= 0 || $total > 999999999999999) {
            throw ValidationException::withMessages(['meal_slot_id' => 'Nilai reservasi tidak didukung.']);
        }

        return ['currency' => 'IDR', 'quantity' => $quantity, 'unit_price' => $slot->price,
            'total_price' => sprintf('%d.%02d', intdiv($total, 100), $total % 100),
            'package_name' => $slot->package_name, 'time_slot' => $slot->time_slot->toIso8601String()];
    }
}
