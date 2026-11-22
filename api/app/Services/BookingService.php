<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\CreativeStatus;
use App\Enums\OrganisationType;
use App\Enums\ReviewStatus;
use App\Exceptions\DomainError;
use App\Models\Booking;
use App\Models\Creative;
use App\Models\Screen;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Repositories\ScreenRepository;
use App\Support\Money;
use App\Support\PlaySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pay-first bookings. First to pay gets the loop time.
 *
 * A booking reserves loop time on a screen: its ad's slots (one, or as many as a longer video
 * needs), `repeats` times in every loop, through the same time window (to the minute) on every day
 * of a date range. It doesn't own a fixed place: each hour the screen packs the booked ads back to
 * back (LoopService), so any free slots can be booked, wherever they were. The ad plays in every
 * loop that starts inside the window.
 * Cost = loops × repeats × slots × price per play, paid in full from the wallet when booking.
 * Only verified advertisers with an approved ad can book. Plays that don't happen (cancelled, ad
 * pulled, screen off) are refunded.
 */
class BookingService
{
    public const MAX_DAYS = 92;

    public const MAX_REPEATS = 3;

    public function __construct(
        private BookingRepository $bookings,
        private ScreenRepository $screens,
        private OrganisationService $orgs,
        private CreativeService $creatives,
        private WalletService $wallets,
        private DocumentService $documents,
        private LoopService $loops,
    ) {}

    /** @return Collection<int, Booking> */
    public function list(User $user, string $orgUuid): Collection
    {
        $org = $this->orgs->membership($user, $orgUuid, OrganisationType::Advertiser)->organisation;

        return $this->bookings->forOrganisation($org);
    }

    public function find(User $user, string $orgUuid, string $uuid): Booking
    {
        $org = $this->orgs->membership($user, $orgUuid, OrganisationType::Advertiser)->organisation;

        return $this->bookings->findForOrganisation($org, $uuid) ?? throw (new ModelNotFoundException)->setModel(Booking::class);
    }

    /**
     * What a booking would cost. The portal shows the same sum live; this is the one that counts.
     * The ad plays $repeats times in every loop that starts inside [$startMinute, $endMinute); an ad
     * that takes $slots slots costs $slots times the screen's price for each play.
     *
     * @return array{days: int, slots: int, repeats: int, plays_per_hour: int, plays_per_day: int, plays_total: int, price_per_play_paisa: int, total_paisa: int}
     */
    public static function quote(Screen $screen, CarbonImmutable $start, CarbonImmutable $end, int $startMinute, int $endMinute, int $slots = 1, int $repeats = 1): array
    {
        $days = (int) $start->diffInDays($end) + 1;
        $perDay = count(PlaySchedule::loopStarts($screen->loop_seconds, $startMinute, $endMinute)) * $repeats;
        $plays = $days * $perDay;
        $price = Money::toPaisa($screen->price_per_play) * $slots;

        return [
            'days' => $days,
            'slots' => $slots,
            'repeats' => $repeats,
            'plays_per_hour' => $screen->playsPerHour() * $repeats,
            'plays_per_day' => $perDay,
            'plays_total' => $plays,
            'price_per_play_paisa' => $price,
            'total_paisa' => $plays * $price,
        ];
    }

    public function book(User $user, string $orgUuid, array $data): Booking
    {
        $org = $this->orgs->membership($user, $orgUuid, OrganisationType::Advertiser, write: true)->organisation;

        $doc = $this->documents->status($user);
        if ($doc !== ReviewStatus::Approved->value) {
            throw new DomainError(
                $doc === ReviewStatus::Pending->value
                    ? 'Your identity document is still being checked. You can book once it is verified.'
                    : 'Verify your identity before booking.',
                403,
                ['document' => [$doc === ReviewStatus::Pending->value ? 'pending' : 'required']],
            );
        }

        $screen = $this->screens->findPublic($data['screen_id']) ?? throw (new ModelNotFoundException)->setModel(Screen::class);
        $creative = $this->creatives->findIn($org, $data['creative_id']);
        $repeats = (int) ($data['repeats'] ?? 1);
        $this->ensureCreativeFits($creative, $screen, $repeats);

        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $startMinute = PlaySchedule::minutes($data['start_time']);
        $endMinute = PlaySchedule::minutes($data['end_time']);
        $this->ensureTimes($screen, $start, $end, $startMinute, $endMinute);

        $need = $screen->slotsFor($creative);
        $q = self::quote($screen, $start, $end, $startMinute, $endMinute, $need, $repeats);
        if ($q['plays_per_day'] === 0) {
            throw new DomainError('That time is too short for your ad to play. Make it longer.', 422, [
                'end_time' => ["Choose at least {$screen->loop_seconds} s."],
            ]);
        }

        return DB::transaction(function () use ($org, $user, $screen, $creative, $start, $end, $startMinute, $endMinute, $q, $need, $repeats) {
            // One booking at a time per screen, so two people paying at once can't overbook it.
            $this->screens->lock($screen);
            $free = $this->loops->freeSlots($screen, $start, $end, $startMinute, $endMinute);
            if ($free < $need * $repeats) {
                throw DomainError::conflict($free === 0
                    ? 'The screen is fully booked at those times. Pick other times or dates.'
                    : "Only {$free} of the screen's slots are free at those times, and your ad needs ".($need * $repeats).'. Pick other times, fewer plays per loop or a shorter ad.');
            }

            $booking = $this->bookings->create([
                'organisation_id' => $org->getKey(),
                'screen_id' => $screen->getKey(),
                'creative_id' => $creative->getKey(),
                'booked_by' => $user->getKey(),
                'slot_count' => $need,
                'repeats' => $repeats,
                'date_range' => Booking::dateRange($start, $end),
                'start_minute' => $startMinute,
                'end_minute' => $endMinute,
                // The screen owner approves it before it plays (OwnerScheduleService::decide).
                'status' => BookingStatus::AwaitingOwner,
                'price_per_play' => Money::toRupees($q['price_per_play_paisa']),
                'plays_total' => $q['plays_total'],
                'price' => Money::toRupees($q['total_paisa']),
            ]);
            $this->wallets->pay(
                $org, $q['total_paisa'],
                "Paid for {$q['plays_total']} plays on {$screen->name}",
                $booking->uuid, $user->getKey(),
            );

            return $booking->load(['screen', 'creative']);
        });
    }

    public function cancel(User $user, string $orgUuid, string $bookingUuid): Booking
    {
        $booking = $this->find($user, $orgUuid, $bookingUuid);
        $this->orgs->membership($user, $orgUuid, OrganisationType::Advertiser, write: true);

        return $this->cancelBooking($booking, 'Booking cancelled', $user->getKey());
    }

    /** @return LengthAwarePaginator<int, Booking> */
    public function paginateForAdmin(?string $status, ?string $search): LengthAwarePaginator
    {
        return $this->bookings->paginateForAdmin($status, $search);
    }

    /** The review team stops a booking (e.g. a complaint); unplayed plays are refunded. */
    public function cancelByAdmin(User $admin, string $bookingUuid): Booking
    {
        $booking = $this->bookings->findByUuid($bookingUuid) ?? throw (new ModelNotFoundException)->setModel(Booking::class);

        return $this->cancelBooking($booking, 'Cancelled by the review team', $admin->getKey());
    }

    /** Money paid for plays that haven't happened. */
    public function remainingPaisa(Booking $b): int
    {
        return max(0, Money::toPaisa($b->price) - $b->plays_done * Money::toPaisa($b->price_per_play));
    }

    /** The ad was pulled: its open bookings end and unplayed plays are refunded. */
    public function creativeRejected(Creative $creative): void
    {
        foreach ($this->bookings->openForCreative($creative) as $booking) {
            $b = $this->bookings->lock($booking);
            $this->close($b, BookingStatus::Rejected, 'Ad removed by the review team');
        }
    }

    /**
     * Move bookings along by the clock: scheduled → live at the first booked hour, → completed after
     * the last one (plays that didn't happen are refunded).
     *
     * @return array{live: int, completed: int}
     */
    public function advance(CarbonImmutable $now): array
    {
        $out = ['live' => 0, 'completed' => 0];
        $today = $now->setTimezone(config('dooh.timezone'))->startOfDay();

        foreach ($this->bookings->dueForUpdate($today) as $booking) {
            DB::transaction(function () use ($booking, $now, &$out) {
                $b = $this->bookings->lock($booking);
                [$startAt, $endAt] = $this->window($b);

                if ($b->status === BookingStatus::AwaitingOwner) {
                    // Never approved before its time ran out: everything back.
                    if ($endAt <= $now) {
                        $this->close($b, BookingStatus::Rejected, 'Not approved in time, refunded');
                        $out['completed']++;
                    }
                } elseif ($endAt <= $now) {
                    $this->close($b, BookingStatus::Completed, 'Booking finished, missed plays refunded');
                    $out['completed']++;
                } elseif ($b->status === BookingStatus::Scheduled && $startAt <= $now) {
                    $b->status = BookingStatus::Live;
                    $this->bookings->save($b);
                    $out['live']++;
                }
            });
        }

        return $out;
    }

    /**
     * When the booking starts and ends, in UTC: the start of the window on the first day and its end
     * on the last day, Nepal time.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(Booking $b): array
    {
        $tz = config('dooh.timezone');

        return [
            CarbonImmutable::parse($b->starts_on->toDateString(), $tz)->addMinutes($b->start_minute)->utc(),
            CarbonImmutable::parse($b->ends_on->toDateString(), $tz)->addMinutes($b->end_minute)->utc(),
        ];
    }

    /**
     * The screen owner's decision on a booking waiting for them. Approve: it plays at its booked
     * times (and its ad counts as reviewed for this screen). Reject: the whole price is refunded.
     */
    public function decideByOwner(Booking $booking, bool $approve, User $owner, ?string $note = null): Booking
    {
        return DB::transaction(function () use ($booking, $approve, $owner, $note) {
            $b = $this->bookings->lock($booking);
            if ($b->status !== BookingStatus::AwaitingOwner) {
                throw DomainError::conflict('This booking is not waiting for approval.');
            }
            if (! $approve) {
                $this->close($b, BookingStatus::Rejected, 'Not accepted by the screen owner'.($note ? " ({$note})" : ''), $owner->getKey());

                return $b;
            }

            $creative = $b->creative;
            if ($creative && $creative->status === CreativeStatus::InReview) {
                $creative->fill(['status' => CreativeStatus::Approved, 'reviewed_by' => $owner->getKey(), 'reviewed_at' => now(), 'review_note' => $note]);
                $this->creatives->save($creative);
            }
            $b->status = BookingStatus::Scheduled;
            $this->bookings->save($b);

            return $b;
        });
    }

    private function cancelBooking(Booking $booking, string $why, int $userId): Booking
    {
        return DB::transaction(function () use ($booking, $why, $userId) {
            $b = $this->bookings->lock($booking);
            if (! $b->status->isCancellable()) {
                throw DomainError::conflict("A {$b->status->value} booking can't be cancelled.");
            }
            $this->close($b, BookingStatus::Cancelled, $why, $userId);

            return $b;
        });
    }

    /** End a locked booking with $status and refund the plays that won't happen. */
    private function close(Booking $b, BookingStatus $status, string $why, ?int $userId = null): void
    {
        $this->wallets->refund($b->organisation, $this->remainingPaisa($b), "{$why}: {$b->screen->name}", $b->uuid, $userId);
        $b->status = $status;
        $b->hold_expires_at = null;
        $this->bookings->save($b);
    }

    private function ensureCreativeFits(Creative $creative, Screen $screen, int $repeats = 1): void
    {
        $fail = fn (string $msg) => new DomainError($msg, 422, ['creative_id' => [$msg]]);

        // Approved ads, and ads still waiting for review: the screen owner sees the ad and approves
        // it together with the booking.
        if (! in_array($creative->status, [CreativeStatus::Approved, CreativeStatus::InReview], true)) {
            throw $fail(match ($creative->status) {
                CreativeStatus::Processing => 'This ad is still being checked. Try again in a minute.',
                CreativeStatus::Failed => 'This ad failed the file checks. Upload a fixed version.',
                default => 'This ad was not approved, so it cannot be booked.',
            });
        }
        if (! $creative->fits($screen)) {
            throw $fail("This ad is {$creative->width_px}×{$creative->height_px} ({$creative->orientation()}), but the screen is {$screen->width_px}×{$screen->height_px} ({$screen->orientation}). Upload a version in the screen's shape.");
        }
        if ($screen->slotsFor($creative) > $screen->slotCount()) {
            throw $fail('Your video is '.round((float) $creative->duration_s)." s, but this screen's whole loop is {$screen->loop_seconds} s. Upload a cut of {$screen->loop_seconds} s or less.");
        }
        if ($screen->slotsFor($creative) * $repeats > $screen->slotCount()) {
            throw new DomainError("Playing it {$repeats} times would take more than the whole loop. Pick fewer plays per loop.", 422, [
                'repeats' => ['Too many plays per loop for this ad.'],
            ]);
        }
        $rule = $creative->category->blockedBy();
        if ($rule && $screen->contentRules->contains(fn ($r) => $r->rule === $rule)) {
            throw $fail('This screen does not show '.str_replace('_', ' ', $creative->category->value).' ads.');
        }
    }

    private function ensureTimes(Screen $screen, CarbonImmutable $start, CarbonImmutable $end, int $startMinute, int $endMinute): void
    {
        [$open, $close] = $screen->bookableMinutes();
        if ($startMinute < $open || $endMinute > $close || $startMinute >= $endMinute) {
            [$o, $c] = [PlaySchedule::time($open), PlaySchedule::time($close)];
            throw new DomainError("Pick a time between {$o} and {$c}, when the screen is on.", 422, [
                'start_time' => ["Choose a time between {$o} and {$c}."],
            ]);
        }
        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            throw new DomainError('Book at most '.self::MAX_DAYS.' days at a time.', 422, ['end_date' => ['Too long.']]);
        }
        $now = CarbonImmutable::now(config('dooh.timezone'));
        if ($start->toDateString() < $now->toDateString()) {
            throw new DomainError('The start date has passed.', 422, ['start_date' => ['Pick today or later.']]);
        }
        if ($start->toDateString() === $now->toDateString() && $startMinute <= $now->hour * 60 + $now->minute) {
            throw new DomainError('That time has already passed today. Pick a later time or start tomorrow.', 422, [
                'start_time' => ['Pick a later time.'],
            ]);
        }
    }
}
