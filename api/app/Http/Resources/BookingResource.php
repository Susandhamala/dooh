<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Support\Money;
use App\Support\PlaySchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    private bool $admin = false;

    /** Admin panel: also who booked it. */
    public function admin(): static
    {
        $this->admin = true;

        return $this;
    }

    /**
     * When the ad runs. The exact second inside each loop is set by the screen (the loop is packed
     * so there are no gaps), so this gives the loops it plays in.
     *
     * @return array{loop_seconds: int, spot_seconds: int, slot_count: int, repeats: int, length_s: int, plays_per_day: int, first_loops: list<string>, last_loop: string|null}
     */
    private function schedule(): array
    {
        $starts = $this->resource->loopStarts();

        return [
            'loop_seconds' => $this->screen->loop_seconds,
            'spot_seconds' => $this->screen->spot_seconds,
            'slot_count' => $this->slot_count,
            'repeats' => $this->repeats,
            'length_s' => $this->slot_count * $this->screen->spot_seconds,
            'plays_per_day' => count($starts) * $this->repeats,
            'first_loops' => array_map(PlaySchedule::clock(...), array_slice($starts, 0, 4)),
            'last_loop' => $starts ? PlaySchedule::clock(end($starts)) : null,
        ];
    }

    public function toArray(Request $request): array
    {
        $open = in_array($this->status, [BookingStatus::Scheduled, BookingStatus::Live], true);
        $unplayed = max(0, Money::toPaisa($this->price) - $this->plays_done * Money::toPaisa($this->price_per_play));

        return [
            'id' => $this->uuid,
            'screen' => $this->whenLoaded('screen', fn () => [
                'id' => $this->screen->uuid,
                'name' => $this->screen->name,
                'address' => $this->screen->address,
                'loop_seconds' => $this->screen->loop_seconds,
                'spot_seconds' => $this->screen->spot_seconds,
            ]),
            'creative' => $this->whenLoaded('creative', fn () => $this->creative ? [
                'id' => $this->creative->uuid,
                'name' => $this->creative->name,
                'kind' => $this->creative->kind,
                'status' => $this->creative->status->value,
            ] : null),
            'organisation' => $this->when($this->admin, fn () => [
                'id' => $this->organisation->uuid,
                'name' => $this->organisation->name,
            ]),
            'start_date' => $this->starts_on->toDateString(),
            'end_date' => $this->ends_on->toDateString(),
            'start_time' => PlaySchedule::time($this->start_minute),
            'end_time' => PlaySchedule::time($this->end_minute),
            // When the ad runs, to the second, so the advertiser knows what they bought.
            'schedule' => $this->whenLoaded('screen', fn () => $this->schedule()),
            'status' => $this->status->value,
            // Slots the ad takes in the loop, and how many times it plays per loop; price_per_play covers the slots.
            'slot_count' => $this->slot_count,
            'repeats' => $this->repeats,
            'price_per_play' => $this->price_per_play,
            'plays_total' => $this->plays_total,
            'plays_done' => $this->plays_done,
            // Paid in full when booked.
            'price' => $this->price,
            // What cancelling now would refund (the plays still to come); 0 once the booking is over.
            'refundable' => Money::toRupees($open ? $unplayed : 0),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
