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
    'opens_at', 'closes_at', 'loop_seconds', 'spot_seconds', 'slot_price', 'audience_notes', 'status',
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

    protected function casts(): array
    {
        return [
            'status' => ScreenStatus::class,
            'lat' => 'float',
            'lng' => 'float',
            'slot_price' => 'decimal:2',
        ];
    }
}
