<?php

namespace App\Services;

use App\Enums\OrganisationType;
use App\Enums\ScreenStatus;
use App\Exceptions\DomainError;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Repositories\ScreenRepository;
use App\Support\PlaySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * The screen owner's side of the loop: a dashboard of their screens, which ads are booked in each
 * hour of a day and where they play, and moving an ad earlier or later in the loop.
 */
class OwnerScheduleService
{
    public function __construct(
        private ScreenService $screens,
        private ScreenRepository $screenRepo,
        private BookingRepository $bookings,
        private LoopService $loops,
        private OrganisationService $orgs,
        private BookingService $bookingService,
    ) {}

    /** Screens, players online, ads running and money earned, for one owner organisation. */
    public function overview(User $user, string $orgUuid): array
    {
        $org = $this->orgs->membership($user, $orgUuid, OrganisationType::Owner)->organisation;
        $screens = $this->screenRepo->forOrganisation($org);
        $online = $screens->filter(fn ($s) => $s->activeDevice?->isOnline())->count();

        return [
            'screens' => $screens->count(),
            'published' => $screens->where('status', ScreenStatus::Active)->count(),
            'players' => $screens->filter(fn ($s) => $s->activeDevice)->count(),
            'players_online' => $online,
            ...$this->bookings->ownerTotals($screens->modelKeys(), $this->today()),
        ];
    }

    /**
     * One screen's day, hour by hour: the ads booked in each hour in the order they play, with
     * their place in the loop (start second), so the owner can see every booked slot.
     */
    public function schedule(User $user, string $orgUuid, string $screenUuid, CarbonImmutable $day): array
    {
        $screen = $this->screens->ownerScreen($user, $orgUuid, $screenUuid);
        $booked = $this->bookings->onScreenOn($screen, $day)->keyBy('id');
        $this->loops->forget();

        $hours = [];
        [$open, $close] = $screen->bookableMinutes();
        foreach (LoopService::hoursOf($open, $close) as $h) {
            $ads = [];
            foreach ($this->loops->positions($screen, $day, $h) as $id => $slots) {
                $b = $booked[$id];
                $ads[] = ['booking' => $b->uuid, 'slots' => $slots, 'slot_count' => $b->slot_count];
            }
            $used = array_sum(array_map(fn ($a) => count($a['slots']) * $a['slot_count'], $ads));
            $hours[] = ['hour' => $h, 'used' => $used, 'ads' => $ads];
        }

        return [
            'date' => $day->toDateString(),
            'loop_seconds' => $screen->loop_seconds,
            'spot_seconds' => $screen->spot_seconds,
            'slots_per_loop' => $screen->slotCount(),
            'bookings' => $booked->values()->map(fn (Booking $b) => $this->row($b))->all(),
            'hours' => $hours,
            // Bookings on this screen (any day) waiting for the owner's approval, oldest first.
            'pending' => $this->bookings->awaitingOn($screen)->map(fn (Booking $b) => $this->row($b))->all(),
        ];
    }

    /**
     * Move a booking one place earlier ('up') or later ('down') among the ads of $hour on $day.
     * The new order applies to every hour both ads share; it never changes how much is booked,
     * so it can't overfill the loop. The screen's next signed playlist picks it up.
     */
    public function move(User $user, string $orgUuid, string $screenUuid, string $bookingUuid, string $direction, CarbonImmutable $day, int $hour): void
    {
        $screen = $this->screens->ownerScreen($user, $orgUuid, $screenUuid);
        $this->orgs->membership($user, $orgUuid, OrganisationType::Owner, write: true);

        DB::transaction(function () use ($screen, $bookingUuid, $direction, $day, $hour) {
            $this->screenRepo->lock($screen);
            $this->loops->forget();

            $order = $this->bookings->stillPlaying($screen, $this->today())->values();
            $moving = $order->firstWhere('uuid', $bookingUuid)
                ?? throw (new ModelNotFoundException)->setModel(Booking::class);

            // The ad next to it in that hour, in play order.
            $inHour = array_keys($this->loops->positions($screen, $day, $hour));
            $at = array_search($moving->getKey(), $inHour, true);
            if ($at === false) {
                throw new DomainError('This ad does not play in that hour.', 422, ['hour' => ['Pick an hour the ad plays in.']]);
            }
            $other = $inHour[$direction === 'up' ? $at - 1 : $at + 1] ?? null;
            $ids = $order->modelKeys();
            $j = $other === null ? false : array_search($other, $ids, true);
            if ($j === false) {
                $msg = $direction === 'up' ? 'This ad already plays first.' : 'This ad already plays last.';
                throw new DomainError($msg, 422, ['direction' => [$msg]]);
            }

            // Swap the two in the screen's overall order, then number everything 1, 2, 3 ...
            $i = array_search($moving->getKey(), $ids, true);
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            foreach ($ids as $n => $id) {
                $b = $order->firstWhere('id', $id);
                if ($b->loop_order !== $n + 1) {
                    $b->loop_order = $n + 1;
                    $this->bookings->save($b);
                }
            }
        });
        $this->loops->forget();
    }

    /** Approve (it plays at its booked times) or reject (refunded) a booking on the owner's screen. */
    public function decide(User $user, string $orgUuid, string $screenUuid, string $bookingUuid, bool $approve, ?string $note): void
    {
        $screen = $this->screens->ownerScreen($user, $orgUuid, $screenUuid);
        $this->orgs->membership($user, $orgUuid, OrganisationType::Owner, write: true);
        $booking = $this->bookings->findOnScreen($screen, $bookingUuid)
            ?? throw (new ModelNotFoundException)->setModel(Booking::class);

        $this->bookingService->decideByOwner($booking, $approve, $user, $note);
        $this->loops->forget();
    }

    /** One booking as the owner sees it: the ad, who booked it and when, and when it plays. */
    private function row(Booking $b): array
    {
        $media = fn (string $variant) => URL::temporarySignedRoute(
            'media.creative', now()->addMinutes(30), ['creative' => $b->creative->uuid, 'variant' => $variant], absolute: false,
        );

        return [
            'id' => $b->uuid,
            'status' => $b->status->value,
            'advertiser' => $b->organisation?->name,
            'booked_by' => $b->bookedBy?->name,
            'booked_by_phone' => $b->bookedBy?->phone,
            'booked_at' => $b->created_at?->toIso8601String(),
            'ad' => $b->creative?->name,
            'kind' => $b->creative?->kind,
            'thumb' => $b->creative?->processed_path && $b->creative->thumb_path ? $media('thumb') : null,
            'media' => $b->creative?->processed_path ? $media('media') : null,
            'start_time' => PlaySchedule::time($b->start_minute),
            'end_time' => PlaySchedule::time($b->end_minute),
            'starts_on' => $b->starts_on->toDateString(),
            'ends_on' => $b->ends_on->toDateString(),
            'slot_count' => $b->slot_count,
            'repeats' => $b->repeats,
            'plays_per_day' => $b->playsPerDay(),
            'plays_total' => $b->plays_total,
            'price' => (string) $b->price,
        ];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('dooh.timezone'))->startOfDay();
    }
}
