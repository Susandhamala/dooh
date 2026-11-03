<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organisation_id', 'campaign_id', 'screen_id', 'creative_id', 'slot_index', 'status', 'price', 'hold_expires_at',
    'date_range', 'start_hour', 'end_hour', 'price_per_play', 'plays_total', 'plays_done',
])]
#[Hidden(['id', 'organisation_id', 'campaign_id', 'screen_id', 'creative_id'])]
class Booking extends Model
{
    use HasPublicUuid;

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }

    public function playLogs(): HasMany
    {
        return $this->hasMany(PlayLog::class);
    }

    /** Plays the advertiser has paid for in each booked day. */
    public function playsPerDay(): int
    {
        return intdiv($this->plays_total, max(1, $this->starts_on->diffInDays($this->ends_on) + 1));
    }

    /** Postgres daterange for the inclusive days [$start, $end]. */
    public static function dateRange(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return sprintf('[%s,%s)', $start->toDateString(), $end->addDay()->toDateString());
    }

    /** First day (inclusive). Postgres normalises dateranges to [start, end). */
    protected function startsOn(): Attribute
    {
        return Attribute::get(fn () => CarbonImmutable::parse(substr(trim($this->date_range, '[)'), 0, 10)));
    }

    /** Last day (inclusive). */
    protected function endsOn(): Attribute
    {
        return Attribute::get(fn () => CarbonImmutable::parse(substr(trim($this->date_range, '[)'), 11, 10))->subDay());
    }

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'price' => 'decimal:2',
            'price_per_play' => 'decimal:2',
            'hold_expires_at' => 'immutable_datetime',
            'start_hour' => 'integer',
            'end_hour' => 'integer',
            'plays_total' => 'integer',
            'plays_done' => 'integer',
        ];
    }
}
