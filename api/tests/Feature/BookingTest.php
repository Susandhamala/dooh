<?php

use App\Models\Booking;
use App\Models\Creative;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BookingService;
use App\Services\PlayService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->user = verifyIdentity(acceptTerms(User::factory()->create()));
    $this->org = Organisation::factory()->withMember($this->user)->create();
    // 06:00–22:00, 120 s loop of 10 s slots → 12 slots, 30 plays per hour, रू 2 per play.
    $this->screen = Screen::factory()->create();
    $this->ad = Creative::factory()->create(['organisation_id' => $this->org, 'uploaded_by' => $this->user]);
    $this->day = now('Asia/Kathmandu')->addDays(3)->toDateString();
    fundWallet($this->org, 5000);
});

function book(array $overrides = []): TestResponse
{
    return actingAsApi(test()->user)->postJson('/api/v1/orgs/'.test()->org->uuid.'/bookings', $overrides + [
        'screen_id' => test()->screen->uuid,
        'creative_id' => test()->ad->uuid,
        'start_date' => test()->day,
        'end_date' => CarbonImmutable::parse(test()->day)->addDays(2)->toDateString(),
        'start_hour' => 8,
        'end_hour' => 10,
    ]);
}

function wallet(): Wallet
{
    return Wallet::where('organisation_id', test()->org->id)->first();
}

it('prices by the play and holds the whole cost in the wallet', function () {
    // 3 days × 2 hours × 30 plays/hour = 180 plays × रू 2 = रू 360.
    book()->assertCreated()
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('data.plays_total', 180)
        ->assertJsonPath('data.price', '360.00')
        ->assertJsonPath('data.held', '360.00')
        ->assertJsonPath('data.start_hour', 8)
        ->assertJsonPath('data.end_hour', 10);

    expect(wallet()->balance_paisa)->toBe(500000)
        ->and(wallet()->reserved_paisa)->toBe(36000)
        ->and(wallet()->availablePaisa())->toBe(464000)
        ->and(wallet()->transactions()->where('type', 'hold')->value('amount_paisa'))->toBe(-36000);
});

it('refuses when the wallet cannot cover the booking, and changes nothing', function () {
    $poor = verifyIdentity(acceptTerms(User::factory()->create()));
    $org = Organisation::factory()->withMember($poor)->create();
    fundWallet($org, 100);
    $ad = Creative::factory()->create(['organisation_id' => $org]);

    actingAsApi($poor)->postJson("/api/v1/orgs/{$org->uuid}/bookings", [
        'screen_id' => $this->screen->uuid, 'creative_id' => $ad->uuid, 'start_date' => $this->day, 'end_date' => $this->day,
        'start_hour' => 6, 'end_hour' => 22,
    ])->assertStatus(402)->assertJsonPath('errors.wallet.0', 'You need रू 860.00 more.');

    expect(Booking::count())->toBe(0)
        ->and(Wallet::where('organisation_id', $org->id)->first()->reserved_paisa)->toBe(0);
});

it('needs an identity document and the accepted terms before booking', function () {
    $user = acceptTerms(User::factory()->create());
    $org = Organisation::factory()->withMember($user)->create();
    $ad = Creative::factory()->create(['organisation_id' => $org]);
    $body = ['screen_id' => $this->screen->uuid, 'creative_id' => $ad->uuid, 'start_date' => $this->day, 'end_date' => $this->day, 'start_hour' => 8, 'end_hour' => 9];

    actingAsApi($user)->postJson("/api/v1/orgs/{$org->uuid}/bookings", $body)
        ->assertForbidden()->assertJsonPath('errors.document.0', 'required');

    $noTerms = verifyIdentity(User::factory()->create());
    $org2 = Organisation::factory()->withMember($noTerms)->create();
    actingAsApi($noTerms)->postJson("/api/v1/orgs/{$org2->uuid}/bookings", $body)
        ->assertForbidden()->assertJsonPath('errors.terms.0', config('terms.version'));
});

it('lets a pending document book, so review can happen in parallel', function () {
    $user = verifyIdentity(acceptTerms(User::factory()->create()), 'pending');
    $org = Organisation::factory()->withMember($user)->create();
    fundWallet($org, 1000);
    $ad = Creative::factory()->create(['organisation_id' => $org]);

    actingAsApi($user)->postJson("/api/v1/orgs/{$org->uuid}/bookings", [
        'screen_id' => $this->screen->uuid, 'creative_id' => $ad->uuid, 'start_date' => $this->day, 'end_date' => $this->day, 'start_hour' => 8, 'end_hour' => 9,
    ])->assertCreated();
});

it('fills a screen hour by hour: overlapping hours use other slots, separate hours reuse slot 0', function () {
    $this->screen->update(['loop_seconds' => 20, 'spot_seconds' => 10]); // 2 slots, 180 plays an hour
    fundWallet($this->org, 20000);

    book(['start_hour' => 8, 'end_hour' => 10])->assertCreated();   // slot 0
    book(['start_hour' => 9, 'end_hour' => 11])->assertCreated();   // slot 1 (9–10 overlaps the first)
    book(['start_hour' => 9, 'end_hour' => 10])->assertConflict();  // both slots taken 9–10
    book(['start_hour' => 10, 'end_hour' => 12])->assertCreated();  // slot 0 is free again from 10:00
    book(['start_hour' => 10, 'end_hour' => 11])->assertConflict(); // both slots taken 10–11

    expect(Booking::orderBy('id')->pluck('slot_index')->all())->toBe([0, 1, 0]);
});

it('shows taken slots per hour in the availability grid', function () {
    book(['start_hour' => 8, 'end_hour' => 10])->assertCreated();

    $grid = $this->getJson("/api/v1/screens/{$this->screen->uuid}/availability?from={$this->day}&to={$this->day}")
        ->assertOk()->json('data');

    expect($grid['hours'])->toBe(range(6, 21))
        ->and($grid['plays_per_hour'])->toBe(30)
        ->and($grid['days'][$this->day][0])->toBe(12)  // 06:00
        ->and($grid['days'][$this->day][2])->toBe(11)  // 08:00
        ->and($grid['days'][$this->day][4])->toBe(12); // 10:00
});

it('refuses hours when the screen is off, and hours that already started today', function () {
    book(['start_hour' => 5, 'end_hour' => 7])->assertUnprocessable()->assertJsonValidationErrors('start_hour');
    book(['start_hour' => 21, 'end_hour' => 23])->assertUnprocessable();

    $this->travelTo(CarbonImmutable::parse('2027-01-10 12:30', 'Asia/Kathmandu'));
    book(['start_date' => '2027-01-10', 'end_date' => '2027-01-10', 'start_hour' => 12, 'end_hour' => 14])
        ->assertUnprocessable()->assertJsonValidationErrors('start_hour');
});

it('only books ads that fit the screen', function () {
    $portrait = Creative::factory()->create(['organisation_id' => $this->org, 'width_px' => 1080, 'height_px' => 1920]);
    book(['creative_id' => $portrait->uuid])->assertUnprocessable()->assertJsonValidationErrors('creative_id');

    $long = Creative::factory()->video(15)->create(['organisation_id' => $this->org]);
    book(['creative_id' => $long->uuid])->assertUnprocessable()->assertJsonPath('errors.creative_id.0', "Your video is 15 s, but this screen's slot is 10 s. Upload a shorter cut.");

    $this->screen->contentRules()->create(['rule' => 'no_alcohol']);
    $beer = Creative::factory()->create(['organisation_id' => $this->org, 'category' => 'alcohol']);
    book(['creative_id' => $beer->uuid])->assertUnprocessable()->assertJsonPath('errors.creative_id.0', 'This screen does not show alcohol ads.');

    $refused = Creative::factory()->create(['organisation_id' => $this->org, 'status' => 'rejected']);
    book(['creative_id' => $refused->uuid])->assertUnprocessable();

    $fourK = Creative::factory()->create(['organisation_id' => $this->org, 'width_px' => 3840, 'height_px' => 2160]);
    book(['creative_id' => $fourK->uuid])->assertCreated(); // same shape, any size
});

it('waits for review when the ad is not approved yet', function () {
    $pending = Creative::factory()->inReview()->create(['organisation_id' => $this->org]);

    book(['creative_id' => $pending->uuid])->assertCreated()->assertJsonPath('data.status', 'checking');
});

it('cancels and gives back what was not played', function () {
    $id = book()->json('data.id');

    actingAsApi($this->user)->postJson("/api/v1/orgs/{$this->org->uuid}/bookings/{$id}/cancel")
        ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.held', '0.00');

    expect(wallet()->reserved_paisa)->toBe(0)->and(wallet()->balance_paisa)->toBe(500000);
});

it('goes live at the first booked hour, charges each play once, and finishes with a refund of unused plays', function () {
    $id = book()->json('data.id');
    $booking = Booking::where('uuid', $id)->first();
    $bookings = app(BookingService::class);
    $plays = app(PlayService::class);

    // 08:29 on the first day: live, 15 plays due (08:00:00 … 08:28:00, one every 2-minute loop).
    $at = CarbonImmutable::parse("{$this->day} 08:29", 'Asia/Kathmandu');
    $this->travelTo($at);
    expect($bookings->advance($at)['live'])->toBe(1);
    expect($plays->simulateDue($at))->toBe(15);
    expect($plays->simulateDue($at))->toBe(0); // same plays are never charged twice

    $booking->refresh();
    expect($booking->plays_done)->toBe(15)
        ->and(wallet()->balance_paisa)->toBe(500000 - 15 * 200)
        ->and(wallet()->reserved_paisa)->toBe(36000 - 15 * 200)
        ->and($booking->playLogs()->count())->toBe(15);

    // After the last hour of the last day: completed, the rest of the hold goes back.
    $end = CarbonImmutable::parse($booking->ends_on->toDateString().' 10:00', 'Asia/Kathmandu');
    $this->travelTo($end);
    expect($bookings->advance($end)['completed'])->toBe(1);
    expect($booking->refresh()->status->value)->toBe('completed')
        ->and(wallet()->reserved_paisa)->toBe(0)
        ->and(wallet()->balance_paisa)->toBe(500000 - 15 * 200);
});

it('never charges more plays than were paid for', function () {
    $id = book(['end_date' => $this->day, 'start_hour' => 8, 'end_hour' => 9])->json('data.id'); // 30 plays
    $booking = Booking::where('uuid', $id)->first();
    $booking->update(['status' => 'live']);

    $times = array_map(fn ($i) => CarbonImmutable::parse("{$this->day} 08:00", 'Asia/Kathmandu')->addMinutes($i), range(0, 59));
    expect(app(PlayService::class)->record($booking, $times, 'device'))->toBe(30);
    expect(wallet()->reserved_paisa)->toBe(0)->and(wallet()->balance_paisa)->toBe(500000 - 30 * 200);
});

it('is protected by the database even if two bookings race for one slot', function () {
    $this->screen->update(['loop_seconds' => 10, 'spot_seconds' => 10]);
    book()->assertCreated();

    expect(fn () => Booking::create([
        'organisation_id' => $this->org->id, 'screen_id' => $this->screen->id, 'creative_id' => $this->ad->id, 'slot_index' => 0,
        'status' => 'scheduled', 'price' => 1, 'start_hour' => 9, 'end_hour' => 11,
        'date_range' => Booking::dateRange(CarbonImmutable::parse($this->day), CarbonImmutable::parse($this->day)),
    ]))->toThrow(QueryException::class);
});
