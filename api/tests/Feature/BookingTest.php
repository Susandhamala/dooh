<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->org = Organisation::factory()->withMember($this->user)->create();
    $this->campaign = Campaign::factory()->create([
        'organisation_id' => $this->org,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ]);
    $this->screen = Screen::factory()->slots(2)->create(['slot_price' => 500]);
    $this->url = "/api/v1/orgs/{$this->org->uuid}/campaigns/{$this->campaign->uuid}/bookings";
    $this->days = fn (int $from, int $to) => [
        'screen_id' => $this->screen->uuid,
        'start_date' => now()->addDays($from)->toDateString(),
        'end_date' => now()->addDays($to)->toDateString(),
    ];
});

it('holds the lowest free slot and prices it per day', function () {
    actingAsApi($this->user)->postJson($this->url, ($this->days)(1, 7))
        ->assertCreated()
        ->assertJsonPath('data.slot_index', 0)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.price', '3500.00')
        ->assertJsonPath('data.start_date', now()->addDay()->toDateString())
        ->assertJsonPath('data.end_date', now()->addDays(7)->toDateString());

    actingAsApi($this->user)->postJson($this->url, ($this->days)(3, 5))
        ->assertCreated()->assertJsonPath('data.slot_index', 1);
});

it('refuses when every slot is taken for any day in the range', function () {
    actingAsApi($this->user)->postJson($this->url, ($this->days)(1, 7))->assertCreated();
    actingAsApi($this->user)->postJson($this->url, ($this->days)(5, 9))->assertCreated();

    actingAsApi($this->user)->postJson($this->url, ($this->days)(7, 8))->assertConflict();
});

it('allows back-to-back bookings on the same slot', function () {
    $screen = Screen::factory()->slots(1)->create();
    $book = fn ($a, $b) => actingAsApi($this->user)->postJson($this->url, [...($this->days)($a, $b), 'screen_id' => $screen->uuid]);

    $book(1, 5)->assertCreated();
    $book(6, 10)->assertCreated()->assertJsonPath('data.slot_index', 0);
    $book(10, 11)->assertConflict();
});

it('frees the slot when a booking is cancelled', function () {
    $screen = Screen::factory()->slots(1)->create();
    $body = [...($this->days)(1, 3), 'screen_id' => $screen->uuid];
    $id = actingAsApi($this->user)->postJson($this->url, $body)->json('data.id');

    actingAsApi($this->user)->postJson("{$this->url}/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    actingAsApi($this->user)->postJson("{$this->url}/{$id}/cancel")->assertConflict();
    actingAsApi($this->user)->postJson($this->url, $body)->assertCreated();
});

it('keeps bookings inside the campaign dates', function () {
    actingAsApi($this->user)->postJson($this->url, ($this->days)(25, 40))
        ->assertUnprocessable()->assertJsonValidationErrors('start_date');
});

it('refuses past dates and screens that are not bookable', function () {
    actingAsApi($this->user)->postJson($this->url, ($this->days)(-2, 3))->assertUnprocessable();

    $draft = Screen::factory()->create(['status' => 'draft']);
    actingAsApi($this->user)->postJson($this->url, [...($this->days)(1, 3), 'screen_id' => $draft->uuid])->assertNotFound();
});

it('releases expired holds on schedule', function () {
    $id = actingAsApi($this->user)->postJson($this->url, ($this->days)(1, 3))->json('data.id');
    $this->travel(16)->minutes();

    $this->artisan('bookings:release-expired-holds')->expectsOutputToContain('1 hold(s) released')->assertSuccessful();
    expect(Booking::where('uuid', $id)->first()->status)->toBe(BookingStatus::Cancelled);
});

it('is protected by the database even if the service is bypassed', function () {
    $insert = fn () => DB::table('bookings')->insert([
        'uuid' => (string) Str::uuid(), 'campaign_id' => $this->campaign->id, 'screen_id' => $this->screen->id,
        'slot_index' => 0, 'status' => 'approved', 'price' => 1, 'date_range' => '[2026-12-01,2026-12-05)',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $insert();
    expect(fn () => DB::transaction($insert))->toThrow(QueryException::class, 'bookings_no_double_booking');
});
