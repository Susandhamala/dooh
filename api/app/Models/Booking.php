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

#[Fillable(['campaign_id', 'screen_id', 'slot_index', 'status', 'price', 'hold_expires_at', 'date_range'])]
#[Hidden(['id', 'campaign_id', 'screen_id', 'creative_id'])]
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
            'hold_expires_at' => 'immutable_datetime',
        ];
    }
}
