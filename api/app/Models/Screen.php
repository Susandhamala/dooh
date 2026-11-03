<?php

namespace App\Models;

use App\Enums\ScreenStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** Number of bookable slots in one loop. */
    public function slotCount(): int
    {
        return intdiv($this->loop_seconds, $this->spot_seconds);
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
