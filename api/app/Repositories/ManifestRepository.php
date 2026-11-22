<?php

namespace App\Repositories;

use App\Enums\BookingStatus;
use App\Enums\CreativeStatus;
use App\Models\Booking;
use App\Models\Creative;
use App\Models\Screen;
use App\Models\ScreenManifest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ManifestRepository
{
    /** @return Collection<int, Screen> screens that have a working device to send manifests to */
    public function screensWithDevices(): Collection
    {
        return Screen::query()->whereHas('activeDevice')->with('manifest')->get();
    }

    /**
     * Bookings that may play on $screen on any day in [$from, $to]: scheduled or live, with an
     * approved, processed ad.
     *
     * @return Collection<int, Booking>
     */
    public function playable(Screen $screen, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->playableQuery($screen)
            ->whereRaw('date_range && ?::daterange', [Booking::dateRange($from, $to)])
            ->with('creative')
            ->orderBy('id')
            ->get();
    }

    /** An ad that is booked to play on $screen now or later (what a device may download). */
    public function playableCreative(Screen $screen, string $creativeUuid): ?Creative
    {
        $booking = $this->playableQuery($screen)
            ->whereHas('creative', fn ($q) => $q->where('uuid', $creativeUuid))
            ->with('creative')
            ->first();

        return $booking?->creative;
    }

    public function forScreen(Screen $screen): ?ScreenManifest
    {
        return ScreenManifest::where('screen_id', $screen->getKey())->first();
    }

    public function save(Screen $screen, array $attributes): ScreenManifest
    {
        return ScreenManifest::updateOrCreate(['screen_id' => $screen->getKey()], $attributes);
    }

    private function playableQuery(Screen $screen): Builder
    {
        return Booking::query()
            ->where('screen_id', $screen->getKey())
            ->whereIn('status', [BookingStatus::Scheduled, BookingStatus::Live])
            ->whereHas('creative', fn ($q) => $q->where('status', CreativeStatus::Approved)->whereNotNull('processed_path')->whereNotNull('sha256'));
    }
}
