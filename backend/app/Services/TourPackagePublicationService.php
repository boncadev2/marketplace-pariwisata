<?php

namespace App\Services;

use App\Exceptions\InvalidTourPackageException;
use App\Models\TourPackage;

class TourPackagePublicationService
{
    public function publish(TourPackage $tourPackage): TourPackage
    {
        if ($tourPackage->meeting_point === '' || $tourPackage->minimum_participants < 1 || $tourPackage->maximum_participants < $tourPackage->minimum_participants) {
            throw new InvalidTourPackageException('Kebijakan peserta dan titik kumpul wajib valid.');
        }

        $items = $tourPackage->itineraryItems;
        if ($items->isEmpty() || $items->max('day_number') > $tourPackage->duration_days) {
            throw new InvalidTourPackageException('Itinerary tidak sesuai durasi paket.');
        }

        foreach ($items->groupBy('day_number') as $dayItems) {
            $latestEnd = null;
            foreach ($dayItems->sortBy('starts_at') as $item) {
                $start = strtotime($item->starts_at);
                if ($latestEnd !== null && $start < $latestEnd) {
                    throw new InvalidTourPackageException('Aktivitas itinerary tidak boleh tumpang tindih.');
                }
                $latestEnd = $start + ($item->duration_minutes * 60);
            }
        }

        $tourPackage->update(['status' => 'published']);

        return $tourPackage->fresh();
    }
}
