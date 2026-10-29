<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\DomainError;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Screen;
use App\Models\User;
use App\Repositories\BookingRepository;
use App\Repositories\ScreenRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /** How long an unpaid draft booking holds its slot. */
    public const HOLD_MINUTES = 15;

    private const EXCLUSION_VIOLATION = '23P01';

    public function __construct(
        private BookingRepository $bookings,
        private ScreenRepository $screens,
        private CampaignService $campaigns,
    ) {}

    /** @return Collection<int, Booking> */
    public function list(User $user, string $orgUuid, string $campaignUuid): Collection
    {
        return $this->bookings->forCampaign($this->campaigns->find($user, $orgUuid, $campaignUuid));
    }

    /**
     * Hold one slot on a screen for [$start, $end]. The lowest free slot index is chosen;
     * the exclusion constraint is the real guard if two requests race for it.
     */
    public function hold(User $user, string $orgUuid, string $campaignUuid, array $data): Booking
    {
        $campaign = $this->campaigns->find($user, $orgUuid, $campaignUuid, write: true);
        $screen = $this->screens->findPublic($data['screen_id'])
            ?? throw (new ModelNotFoundException)->setModel(Screen::class);

        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $this->ensureWithinCampaign($campaign, $start, $end);

        $days = $start->diffInDays($end) + 1;
        // Integer paisa so no float rounding creeps into prices.
        $paisa = (int) round((float) $screen->slot_price * 100) * $days;
        $price = sprintf('%d.%02d', intdiv($paisa, 100), $paisa % 100);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $taken = $this->bookings->takenSlots($screen, $start, $end);
            $free = array_values(array_diff(range(0, $screen->slotCount() - 1), $taken));
            if ($free === []) {
                throw DomainError::conflict('No free slot on this screen for the whole date range.');
            }

            try {
                return DB::transaction(fn () => $this->bookings->create([
                    'campaign_id' => $campaign->getKey(),
                    'screen_id' => $screen->getKey(),
                    'slot_index' => $free[0],
                    'date_range' => Booking::dateRange($start, $end),
                    'status' => BookingStatus::Draft,
                    'price' => $price,
                    'hold_expires_at' => now()->addMinutes(self::HOLD_MINUTES),
                ]))->load('screen');
            } catch (QueryException $e) {
                if ($e->getCode() !== self::EXCLUSION_VIOLATION) {
                    throw $e;
                }
                // Someone took that slot between our read and insert; look again.
            }
        }

        throw DomainError::conflict('This screen is busy right now. Please try again.');
    }

    public function cancel(User $user, string $orgUuid, string $campaignUuid, string $bookingUuid): Booking
    {
        $campaign = $this->campaigns->find($user, $orgUuid, $campaignUuid, write: true);
        $booking = $this->bookings->findForCampaign($campaign, $bookingUuid)
            ?? throw (new ModelNotFoundException)->setModel(Booking::class);

        if (! $booking->status->isCancellable()) {
            throw DomainError::conflict("A {$booking->status->value} booking can't be cancelled.");
        }

        return $this->bookings->setStatus($booking, BookingStatus::Cancelled);
    }

    public function releaseExpiredHolds(): int
    {
        return $this->bookings->releaseExpiredHolds(CarbonImmutable::now());
    }

    private function ensureWithinCampaign(Campaign $campaign, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if ($start->lt($campaign->start_date) || $end->gt($campaign->end_date)) {
            throw new DomainError('Booking dates must fall within the campaign dates.', 422, [
                'start_date' => ['Must be within the campaign dates.'],
            ]);
        }
    }
}
