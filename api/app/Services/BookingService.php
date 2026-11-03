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
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hourly, pay-per-play bookings.
 *
 * A booking takes one slot in the screen's loop, for the same hours on every day of a date range.
 * Cost = days × hours × plays per hour × price per play. The whole cost is held in the wallet when
 * booking; each play is charged from the hold; whatever is left is released when the booking ends,
 * is cancelled, or its ad is rejected.
 */
class BookingService
{
    /** How long an old-style unpaid draft held its slot (kept for the release command). */
    public const HOLD_MINUTES = 15;

    public const MAX_DAYS = 92;

    private const EXCLUSION_VIOLATION = '23P01';

    public function __construct(
        private BookingRepository $bookings,
        private ScreenRepository $screens,
        private OrganisationService $orgs,
        private CreativeService $creatives,
        private WalletService $wallets,
        private DocumentService $documents,
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
     *
     * @return array{days: int, hours: int, plays_per_hour: int, plays_per_day: int, plays_total: int, price_per_play_paisa: int, total_paisa: int}
     */
    public static function quote(Screen $screen, CarbonImmutable $start, CarbonImmutable $end, int $startHour, int $endHour): array
    {
        $days = (int) $start->diffInDays($end) + 1;
        $hours = $endHour - $startHour;
        $perHour = $screen->playsPerHour();
        $plays = $days * $hours * $perHour;
        $price = Money::toPaisa($screen->price_per_play);

        return [
            'days' => $days,
            'hours' => $hours,
            'plays_per_hour' => $perHour,
            'plays_per_day' => $hours * $perHour,
            'plays_total' => $plays,
            'price_per_play_paisa' => $price,
            'total_paisa' => $plays * $price,
        ];
    }

    public function book(User $user, string $orgUuid, array $data): Booking
    {
        $org = $this->orgs->membership($user, $orgUuid, OrganisationType::Advertiser, write: true)->organisation;

        if (! in_array($this->documents->status($user), [ReviewStatus::Pending->value, ReviewStatus::Approved->value], true)) {
            throw new DomainError('Add your identity document before booking.', 403, ['document' => ['required']]);
        }

        $screen = $this->screens->findPublic($data['screen_id']) ?? throw (new ModelNotFoundException)->setModel(Screen::class);
        $creative = $this->creatives->findIn($org, $data['creative_id']);
        $this->ensureCreativeFits($creative, $screen);

        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $startHour = (int) $data['start_hour'];
        $endHour = (int) $data['end_hour'];
        $this->ensureTimes($screen, $start, $end, $startHour, $endHour);

        $q = self::quote($screen, $start, $end, $startHour, $endHour);
        $status = $creative->status === CreativeStatus::Approved ? BookingStatus::Scheduled : BookingStatus::Checking;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $taken = $this->bookings->takenSlots($screen, $start, $end, $startHour, $endHour);
            $free = array_values(array_diff(range(0, $screen->slotCount() - 1), $taken));
            if ($free === []) {
                throw DomainError::conflict('Those hours are fully booked on at least one day. Pick other hours or dates.');
            }

            try {
                return DB::transaction(function () use ($org, $user, $screen, $creative, $start, $end, $startHour, $endHour, $q, $status, $free) {
                    $booking = $this->bookings->create([
                        'organisation_id' => $org->getKey(),
                        'screen_id' => $screen->getKey(),
                        'creative_id' => $creative->getKey(),
                        'slot_index' => $free[0],
                        'date_range' => Booking::dateRange($start, $end),
                        'start_hour' => $startHour,
                        'end_hour' => $endHour,
                        'status' => $status,
                        'price_per_play' => $screen->price_per_play,
                        'plays_total' => $q['plays_total'],
                        'price' => Money::toRupees($q['total_paisa']),
                    ]);
                    $this->wallets->hold(
                        $org, $q['total_paisa'],
                        "Held for {$q['plays_total']} plays on {$screen->name}",
                        $booking->uuid, $user->getKey(),
                    );

                    return $booking->load(['screen', 'creative']);
                });
            } catch (QueryException $e) {
                if ($e->getCode() !== self::EXCLUSION_VIOLATION) {
                    throw $e;
                }
                // Someone took that slot between our read and insert; look again.
            }
        }

        throw DomainError::conflict('This screen is busy right now. Please try again.');
    }

    public function cancel(User $user, string $orgUuid, string $bookingUuid): Booking
    {
        $booking = $this->find($user, $orgUuid, $bookingUuid);
        $this->orgs->membership($user, $orgUuid, OrganisationType::Advertiser, write: true);

        return DB::transaction(function () use ($booking, $user) {
            $b = $this->bookings->lock($booking);
            if (! $b->status->isCancellable()) {
                throw DomainError::conflict("A {$b->status->value} booking can't be cancelled.");
            }
            $this->close($b, BookingStatus::Cancelled, 'Booking cancelled', $user->getKey());

            return $b;
        });
    }

    /** Money still held for plays that haven't happened. */
    public function remainingPaisa(Booking $b): int
    {
        return max(0, Money::toPaisa($b->price) - $b->plays_done * Money::toPaisa($b->price_per_play));
    }

    /** The review team approved the ad: bookings that were waiting on it are scheduled. */
    public function creativeApproved(Creative $creative): void
    {
        foreach ($this->bookings->waitingOnCreative($creative) as $booking) {
            $b = $this->bookings->lock($booking);
            if ($b->status === BookingStatus::Checking) {
                $b->status = BookingStatus::Scheduled;
                $this->bookings->save($b);
            }
        }
    }

    /** The ad was refused (or pulled): its open bookings end and the unused money goes back. */
    public function creativeRejected(Creative $creative): void
    {
        foreach ($this->bookings->openForCreative($creative) as $booking) {
            $b = $this->bookings->lock($booking);
            $this->close($b, BookingStatus::Rejected, 'Ad not approved');
        }
    }

    /**
     * Move bookings along by the clock: scheduled → live at the first booked hour, → completed after
     * the last one (unused money released). Bookings whose ad was never approved in time are cancelled.
     *
     * @return array{live: int, completed: int, expired: int}
     */
    public function advance(CarbonImmutable $now): array
    {
        $out = ['live' => 0, 'completed' => 0, 'expired' => 0];
        $today = $now->setTimezone(config('dooh.timezone'))->startOfDay();

        foreach ($this->bookings->dueForUpdate($today) as $booking) {
            DB::transaction(function () use ($booking, $now, &$out) {
                $b = $this->bookings->lock($booking);
                [$startAt, $endAt] = $this->window($b);

                if ($b->status === BookingStatus::Checking && $endAt <= $now) {
                    $this->close($b, BookingStatus::Cancelled, 'Ad was not approved in time');
                    $out['expired']++;
                } elseif (in_array($b->status, [BookingStatus::Scheduled, BookingStatus::Live], true) && $endAt <= $now) {
                    $this->close($b, BookingStatus::Completed, 'Booking finished, unused plays returned');
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
     * When the booking starts and ends, in UTC: the first booked hour of the first day and the end of
     * the last booked hour of the last day, Nepal time.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function window(Booking $b): array
    {
        $tz = config('dooh.timezone');

        return [
            CarbonImmutable::parse($b->starts_on->toDateString(), $tz)->addHours($b->start_hour)->utc(),
            CarbonImmutable::parse($b->ends_on->toDateString(), $tz)->addHours($b->end_hour)->utc(),
        ];
    }

    public function releaseExpiredHolds(): int
    {
        return $this->bookings->releaseExpiredHolds(CarbonImmutable::now());
    }

    /** End a locked booking with $status and give back what it still holds. */
    private function close(Booking $b, BookingStatus $status, string $why, ?int $userId = null): void
    {
        $this->wallets->release($b->organisation, $this->remainingPaisa($b), "{$why}: {$b->screen->name}", $b->uuid, $userId);
        $b->status = $status;
        $b->hold_expires_at = null;
        $this->bookings->save($b);
    }

    private function ensureCreativeFits(Creative $creative, Screen $screen): void
    {
        $fail = fn (string $msg) => new DomainError($msg, 422, ['creative_id' => [$msg]]);

        if (! $creative->status->isBookable()) {
            throw $fail(match ($creative->status) {
                CreativeStatus::Processing => 'This ad is still being checked. Try again in a minute.',
                CreativeStatus::Failed => 'This ad failed the file checks. Upload a fixed version.',
                default => 'This ad was not approved, so it cannot be booked.',
            });
        }
        if (! $creative->fits($screen)) {
            throw $fail("This ad is {$creative->width_px}×{$creative->height_px} ({$creative->orientation()}), but the screen is {$screen->width_px}×{$screen->height_px} ({$screen->orientation}). Upload a version in the screen's shape.");
        }
        if ($creative->kind === 'video' && $creative->duration_s > $screen->spot_seconds + 0.5) {
            throw $fail('Your video is '.round((float) $creative->duration_s)." s, but this screen's slot is {$screen->spot_seconds} s. Upload a shorter cut.");
        }
        $rule = $creative->category->blockedBy();
        if ($rule && $screen->contentRules->contains(fn ($r) => $r->rule === $rule)) {
            throw $fail('This screen does not show '.str_replace('_', ' ', $creative->category->value).' ads.');
        }
    }

    private function ensureTimes(Screen $screen, CarbonImmutable $start, CarbonImmutable $end, int $startHour, int $endHour): void
    {
        [$open, $close] = $screen->bookableHours();
        if ($startHour < $open || $endHour > $close || $startHour >= $endHour) {
            throw new DomainError("Pick hours between {$open}:00 and {$close}:00, when the screen is on.", 422, [
                'start_hour' => ["Choose hours between {$open}:00 and {$close}:00."],
            ]);
        }
        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            throw new DomainError('Book at most '.self::MAX_DAYS.' days at a time.', 422, ['end_date' => ['Too long.']]);
        }
        $now = CarbonImmutable::now(config('dooh.timezone'));
        if ($start->toDateString() < $now->toDateString()) {
            throw new DomainError('The start date has passed.', 422, ['start_date' => ['Pick today or later.']]);
        }
        if ($start->toDateString() === $now->toDateString() && $startHour <= $now->hour) {
            throw new DomainError('That hour has already started today. Pick a later hour or start tomorrow.', 422, [
                'start_hour' => ['Pick a later hour.'],
            ]);
        }
    }
}
