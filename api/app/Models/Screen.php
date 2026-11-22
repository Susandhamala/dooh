<?php

namespace App\Models;

use App\Enums\ScreenStatus;
use App\Models\Concerns\HasPublicUuid;
use App\Support\PlaySchedule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name', 'description', 'address', 'lat', 'lng', 'width_px', 'height_px', 'orientation',
    'opens_at', 'closes_at', 'loop_seconds', 'spot_seconds', 'price_per_play', 'audience_notes', 'status',
])]
#[Hidden(['id', 'organisation_id'])]
class Screen extends Model
{
    use HasFactory, HasPublicUuid;

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function contentRules(): HasMany
    {
        return $this->hasMany(ContentRule::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** The player currently allowed to fetch this screen's manifest (at most one). */
    public function activeDevice(): HasOne
    {
        return $this->hasOne(Device::class)->whereNull('revoked_at');
    }

    public function manifest(): HasOne
    {
        return $this->hasOne(ScreenManifest::class);
    }

    /** Number of bookable slots in one loop. */
    public function slotCount(): int
    {
        return intdiv($this->loop_seconds, $this->spot_seconds);
    }

    /**
     * Back-to-back slots $creative takes in this screen's loop: one for an image, enough to cover a
     * video's length otherwise (half a second of slack, so a 10.3 s cut still fits a 10 s slot).
     */
    public function slotsFor(Creative $creative): int
    {
        if ($creative->kind !== 'video') {
            return 1;
        }

        return max(1, (int) ceil(max(0.0, (float) $creative->duration_s - 0.5) / $this->spot_seconds));
    }

    /** Times the loop runs in one hour: each slot plays once per loop. */
    public function playsPerHour(): int
    {
        return intdiv(3600, $this->loop_seconds);
    }

    /**
     * Whole hours that can be booked: from the first full hour after opening to the last full hour
     * before closing, e.g. 06:00–22:00 gives [6, 22).
     *
     * @return array{0: int, 1: int}
     */
    public function bookableHours(): array
    {
        [$oh, $om] = array_map('intval', explode(':', substr((string) $this->opens_at, 0, 5)));
        [$ch] = array_map('intval', explode(':', substr((string) $this->closes_at, 0, 5)));
        $first = $om > 0 ? $oh + 1 : $oh;
        $last = (string) $this->closes_at === '24:00' ? 24 : $ch;

        return [$first, max($first, $last)];
    }

    /**
     * Minutes after midnight when the screen is on: [opens_at, closes_at). A screen that closes at
     * 00:00 or 24:00 runs to the end of the day.
     *
     * @return array{0: int, 1: int}
     */
    public function bookableMinutes(): array
    {
        $open = PlaySchedule::minutes((string) $this->opens_at);
        $close = PlaySchedule::minutes((string) $this->closes_at);

        return [$open, $close === 0 ? 1440 : max($open, $close)];
    }

    protected function casts(): array
    {
        return [
            'status' => ScreenStatus::class,
            'lat' => 'float',
            'lng' => 'float',
            'price_per_play' => 'decimal:2',
        ];
    }
}
