<?php

use App\Models\Booking;
use App\Models\Creative;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BookingService;
use App\Services\LoopService;
use App\Services\PlayService;
use Carbon\CarbonImmutable;
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
        'start_time' => '08:00',
        'end_time' => '10:00',
    ]);
}

function wallet(): Wallet
{
    return Wallet::where('organisation_id', test()->org->id)->first();
}

it('prices by the play and takes the whole cost from the wallet at booking', function () {
    // 3 days × 2 hours × 30 plays/hour = 180 plays × रू 2 = रू 360.
    book()->assertCreated()
        ->assertJsonPath('data.status', 'awaiting_owner')
        ->assertJsonPath('data.plays_total', 180)
        ->assertJsonPath('data.price', '360.00')
        ->assertJsonPath('data.refundable', '360.00')
        ->assertJsonPath('data.start_time', '08:00')
        ->assertJsonPath('data.end_time', '10:00');

    expect(wallet()->balance_paisa)->toBe(500000 - 36000)
        ->and(wallet()->reserved_paisa)->toBe(0)
        ->and(wallet()->transactions()->where('type', 'charge')->value('amount_paisa'))->toBe(-36000);
});

it('refuses when the wallet cannot cover the booking, and changes nothing', function () {
    $poor = verifyIdentity(acceptTerms(User::factory()->create()));
    $org = Organisation::factory()->withMember($poor)->create();
    fundWallet($org, 100);
    $ad = Creative::factory()->create(['organisation_id' => $org]);

    actingAsApi($poor)->postJson("/api/v1/orgs/{$org->uuid}/bookings", [
        'screen_id' => $this->screen->uuid, 'creative_id' => $ad->uuid, 'start_date' => $this->day, 'end_date' => $this->day,
        'start_time' => '06:00', 'end_time' => '22:00',
    ])->assertStatus(402)->assertJsonPath('errors.wallet.0', 'You need रू 860.00 more.');

    expect(Booking::count())->toBe(0)
        ->and(Wallet::where('organisation_id', $org->id)->first()->balance_paisa)->toBe(10000);
});

it('needs a verified identity and the accepted terms before booking', function () {
    $body = fn ($ad) => ['screen_id' => $this->screen->uuid, 'creative_id' => $ad->uuid, 'start_date' => $this->day, 'end_date' => $this->day, 'start_time' => '08:00', 'end_time' => '09:00'];

    $user = acceptTerms(User::factory()->create());
    $org = Organisation::factory()->withMember($user)->create();
    $ad = Creative::factory()->create(['organisation_id' => $org]);
    actingAsApi($user)->postJson("/api/v1/orgs/{$org->uuid}/bookings", $body($ad))
        ->assertForbidden()->assertJsonPath('errors.document.0', 'required');

    // Still being checked is not enough.
    verifyIdentity($user, 'pending');
    fundWallet($org, 1000);
    actingAsApi($user)->postJson("/api/v1/orgs/{$org->uuid}/bookings", $body($ad))
        ->assertForbidden()->assertJsonPath('errors.document.0', 'pending');

    $noTerms = verifyIdentity(User::factory()->create());
    $org2 = Organisation::factory()->withMember($noTerms)->create();
    actingAsApi($noTerms)->postJson("/api/v1/orgs/{$org2->uuid}/bookings", $body($ad))
        ->assertForbidden()->assertJsonPath('errors.terms.0', config('terms.version'));
});

it('first to pay gets the loop time: overlapping hours share the loop, a full hour is refused', function () {
    $this->screen->update(['loop_seconds' => 20, 'spot_seconds' => 10]); // 2 slots, 180 plays an hour
    fundWallet($this->org, 20000);

    book(['start_time' => '08:00', 'end_time' => '10:00'])->assertCreated();
    book(['start_time' => '09:00', 'end_time' => '11:00'])->assertCreated();   // 9–10 overlaps the first: both slots used
    book(['start_time' => '09:00', 'end_time' => '10:00'])->assertConflict()
        ->assertJsonPath('message', 'The screen is fully booked at those times. Pick other times or dates.');
    book(['start_time' => '10:00', 'end_time' => '12:00'])->assertCreated();  // one slot free again from 10:00
    book(['start_time' => '10:00', 'end_time' => '11:00'])->assertConflict(); // both slots used 10–11

    expect(Booking::count())->toBe(3)
        ->and(wallet()->transactions()->where('type', 'charge')->count())->toBe(3); // refused ones paid nothing
});

it('shows taken slots per hour in the availability grid', function () {
    book(['start_time' => '08:00', 'end_time' => '10:00'])->assertCreated();

    $grid = $this->getJson("/api/v1/screens/{$this->screen->uuid}/availability?from={$this->day}&to={$this->day}")
        ->assertOk()->json('data');

    expect($grid['hours'])->toBe(range(6, 21))
        ->and($grid['plays_per_hour'])->toBe(30)
        ->and($grid['days'][$this->day][0])->toBe(12)  // 06:00
        ->and($grid['days'][$this->day][2])->toBe(11)  // 08:00
        ->and($grid['days'][$this->day][4])->toBe(12); // 10:00
});

it('refuses hours when the screen is off, and hours that already started today', function () {
    book(['start_time' => '05:00', 'end_time' => '07:00'])->assertUnprocessable()->assertJsonValidationErrors('start_time');
    book(['start_time' => '21:00', 'end_time' => '23:00'])->assertUnprocessable();

    $this->travelTo(CarbonImmutable::parse('2027-01-10 12:30', 'Asia/Kathmandu'));
    book(['start_date' => '2027-01-10', 'end_date' => '2027-01-10', 'start_time' => '12:00', 'end_time' => '14:00'])
        ->assertUnprocessable()->assertJsonValidationErrors('start_time');
});

it('only books approved ads that fit the screen', function () {
    $portrait = Creative::factory()->create(['organisation_id' => $this->org, 'width_px' => 1080, 'height_px' => 1920]);
    book(['creative_id' => $portrait->uuid])->assertUnprocessable()->assertJsonValidationErrors('creative_id');

    $tooLong = Creative::factory()->video(150)->create(['organisation_id' => $this->org]);
    book(['creative_id' => $tooLong->uuid])->assertUnprocessable()->assertJsonPath('errors.creative_id.0', "Your video is 150 s, but this screen's whole loop is 120 s. Upload a cut of 120 s or less.");

    $this->screen->contentRules()->create(['rule' => 'no_alcohol']);
    $beer = Creative::factory()->create(['organisation_id' => $this->org, 'category' => 'alcohol']);
    book(['creative_id' => $beer->uuid])->assertUnprocessable()->assertJsonPath('errors.creative_id.0', 'This screen does not show alcohol ads.');

    $refused = Creative::factory()->create(['organisation_id' => $this->org, 'status' => 'rejected']);
    book(['creative_id' => $refused->uuid])->assertUnprocessable();

    $waiting = Creative::factory()->inReview()->create(['organisation_id' => $this->org]);
    // An ad still in review can be booked: the screen owner approves ad and booking together.
    book(['creative_id' => $waiting->uuid, 'start_time' => '12:00', 'end_time' => '13:00'])->assertCreated()->assertJsonPath('data.status', 'awaiting_owner');

    $fourK = Creative::factory()->create(['organisation_id' => $this->org, 'width_px' => 3840, 'height_px' => 2160]);
    book(['creative_id' => $fourK->uuid])->assertCreated(); // same shape, any size
});

it('cancels and refunds the plays that have not happened', function () {
    $id = book()->json('data.id');

    actingAsApi($this->user)->postJson("/api/v1/orgs/{$this->org->uuid}/bookings/{$id}/cancel")
        ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.refundable', '0.00');

    expect(wallet()->balance_paisa)->toBe(500000)
        ->and(wallet()->transactions()->where('type', 'refund')->value('amount_paisa'))->toBe(36000);
});

it('goes live at the first booked hour, counts each play once, and refunds missed plays at the end', function () {
    $id = book()->json('data.id');
    $booking = Booking::where('uuid', $id)->first();
    $booking->update(['status' => 'scheduled']); // the screen owner approved it
    $bookings = app(BookingService::class);
    $plays = app(PlayService::class);

    // 08:29 on the first day: live, 15 plays due (08:00:00 … 08:28:00, one every 2-minute loop).
    $at = CarbonImmutable::parse("{$this->day} 08:29", 'Asia/Kathmandu');
    $this->travelTo($at);
    expect($bookings->advance($at)['live'])->toBe(1);
    expect($plays->simulateDue($at))->toBe(15);
    expect($plays->simulateDue($at))->toBe(0); // same plays are never counted twice

    $booking->refresh();
    expect($booking->plays_done)->toBe(15)
        ->and(wallet()->balance_paisa)->toBe(500000 - 36000) // already paid; plays don't charge again
        ->and($booking->playLogs()->count())->toBe(15);

    // After the last hour of the last day (screen "went off" after 15 plays): the other 165 are refunded.
    $end = CarbonImmutable::parse($booking->ends_on->toDateString().' 10:00', 'Asia/Kathmandu');
    $this->travelTo($end);
    expect($bookings->advance($end)['completed'])->toBe(1);
    expect($booking->refresh()->status->value)->toBe('completed')
        ->and(wallet()->balance_paisa)->toBe(500000 - 15 * 200);
});

it('never counts more plays than were paid for', function () {
    $id = book(['end_date' => $this->day, 'start_time' => '08:00', 'end_time' => '09:00'])->json('data.id'); // 30 plays
    $booking = Booking::where('uuid', $id)->first();
    $booking->update(['status' => 'live']);

    $times = array_map(fn ($i) => CarbonImmutable::parse("{$this->day} 08:00", 'Asia/Kathmandu')->addMinutes($i), range(0, 59));
    expect(app(PlayService::class)->record($booking, $times, 'device'))->toBe(30)
        ->and($booking->refresh()->plays_done)->toBe(30)
        ->and(wallet()->balance_paisa)->toBe(500000 - 30 * 200);
});

it('books a video of any length: it takes several slots and pays for each', function () {
    // 34 s on 10 s slots → 4 slots. 3 days × 2 hours × 30 plays = 180 plays × रू 2 × 4 = रू 1,440.
    $long = Creative::factory()->video(34)->create(['organisation_id' => $this->org]);
    book(['creative_id' => $long->uuid])->assertCreated()
        ->assertJsonPath('data.slot_count', 4)
        ->assertJsonPath('data.plays_total', 180)
        ->assertJsonPath('data.price_per_play', '8.00')
        ->assertJsonPath('data.price', '1440.00');

    expect(wallet()->balance_paisa)->toBe(500000 - 144000)
        // A 10.3 s cut still fits one 10 s slot.
        ->and($this->screen->slotsFor(Creative::factory()->video(10.3)->make()))->toBe(1);
});

it('packs the loop: gaps between ads merge, so an ad fits whenever enough slots are free', function () {
    $this->screen->update(['loop_seconds' => 40, 'spot_seconds' => 10]); // 4 slots
    fundWallet($this->org, 50000);
    $twenty = Creative::factory()->video(20)->create(['organisation_id' => $this->org]); // 2 slots

    $a = book(['start_time' => '08:00', 'end_time' => '09:00'])->assertCreated()->json('data.id'); // A
    book(['start_time' => '08:00', 'end_time' => '09:00'])->assertCreated();                        // B
    book(['start_time' => '08:00', 'end_time' => '09:00'])->assertCreated();                        // C
    actingAsApi($this->user)->postJson("/api/v1/orgs/{$this->org->uuid}/bookings/{$a}/cancel")->assertOk(); // gap where A was

    // One slot free before B and one after C: with fixed places a 2-slot ad wouldn't fit; packed, it does.
    book(['creative_id' => $twenty->uuid, 'start_time' => '08:00', 'end_time' => '09:00'])->assertCreated();
    book(['start_time' => '08:00', 'end_time' => '09:00'])->assertConflict()
        ->assertJsonPath('message', 'The screen is fully booked at those times. Pick other times or dates.');

    $loops = app(LoopService::class);
    $places = $loops->positions($this->screen->fresh(), CarbonImmutable::parse($this->day), 8);
    expect(array_values($places))->toBe([[0], [1], [2]]); // B, C, then the video on slots 2–3: no gaps

    $grid = $this->getJson("/api/v1/screens/{$this->screen->uuid}/availability?from={$this->day}&to={$this->day}")->json('data');
    expect($grid['days'][$this->day][2])->toBe(0)  // 08:00 full
        ->and($grid['days'][$this->day][4])->toBe(4); // 10:00 free
});

it('plays the same ad several times a loop, and lets an ad be booked again on the same screen', function () {
    fundWallet($this->org, 20000);
    // 2 plays per loop: 1 day × 1 hour × 30 loops × 2 = 60 plays × रू 2 = रू 120.
    $res = book(['end_date' => $this->day, 'start_time' => '08:00', 'end_time' => '09:00', 'repeats' => 2])->assertCreated()
        ->assertJsonPath('data.repeats', 2)
        ->assertJsonPath('data.plays_total', 60)
        ->assertJsonPath('data.price', '120.00')
        ->assertJsonPath('data.schedule.plays_per_day', 60);
    // The same ad again, same screen, overlapping times: it simply takes more of the loop.
    book(['end_date' => $this->day, 'start_time' => '08:30', 'end_time' => '09:30'])->assertCreated();

    $loops = app(LoopService::class);
    $places = $loops->positions($this->screen->fresh(), CarbonImmutable::parse($this->day), 8);
    expect(array_values($places))->toBe([[0, 2], [1]]); // first copies first, so the two plays are spread

    $booking = Booking::where('uuid', $res->json('data.id'))->first()->load('screen');
    expect(array_slice($loops->playTimes($booking, CarbonImmutable::parse($this->day)), 0, 4))
        ->toBe([8 * 3600, 8 * 3600 + 20, 8 * 3600 + 120, 8 * 3600 + 140]);

    // Too many plays for the loop is refused up front.
    $long = Creative::factory()->video(60)->create(['organisation_id' => $this->org]); // 6 of 12 slots
    book(['creative_id' => $long->uuid, 'repeats' => 3])->assertUnprocessable()->assertJsonValidationErrors('repeats');
    book(['repeats' => 4])->assertUnprocessable()->assertJsonValidationErrors('repeats');
});

it('books to the minute and counts only the loops that start inside the window', function () {
    // 08:15–08:45 on a 120 s loop: loops start at 08:16, 08:18 … 08:44 → 15 plays a day.
    book(['end_date' => $this->day, 'start_time' => '08:15', 'end_time' => '08:45'])->assertCreated()
        ->assertJsonPath('data.start_time', '08:15')
        ->assertJsonPath('data.end_time', '08:45')
        ->assertJsonPath('data.plays_total', 15)
        ->assertJsonPath('data.price', '30.00')
        ->assertJsonPath('data.schedule.first_loops', ['08:16:00', '08:18:00', '08:20:00', '08:22:00'])
        ->assertJsonPath('data.schedule.last_loop', '08:44:00');

    // 09:01–09:02 holds no loop start (loops start at 09:00 and 09:02), so the ad would never play.
    book(['end_date' => $this->day, 'start_time' => '09:01', 'end_time' => '09:02'])->assertUnprocessable()
        ->assertJsonValidationErrors('end_time');
});
