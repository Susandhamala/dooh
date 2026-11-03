<?php

use App\Models\Booking;
use App\Models\Creative;
use App\Models\Organisation;
use App\Models\Screen;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('local');
    $this->user = verifyIdentity(acceptTerms(User::factory()->create()));
    $this->org = Organisation::factory()->withMember($this->user)->create();
    $this->url = "/api/v1/orgs/{$this->org->uuid}/creatives";
    $this->admin = User::factory()->withMfa()->create()->assignRole('admin');
});

function upload(UploadedFile $file, array $extra = []): TestResponse
{
    return actingAsApi(test()->user)->post(test()->url, $extra + [
        'name' => 'Dashain offer', 'category' => 'retail', 'resolution' => '1920x1080', 'declaration' => '1', 'file' => $file,
    ], ['Accept' => 'application/json']);
}

it('checks, re-encodes and queues an image for review', function () {
    // queue is sync in tests, so processing has finished when the request returns
    $id = upload(UploadedFile::fake()->image('ad.jpg', 3840, 2160))->assertCreated()->json('data.id');

    $ad = Creative::where('uuid', $id)->first();
    expect($ad->status->value)->toBe('in_review')
        ->and($ad->mime)->toBe('image/webp')
        ->and($ad->sha256)->toHaveLength(64)
        ->and(getimagesize(Storage::disk('local')->path($ad->processed_path)))->toMatchArray([0 => 1920, 1 => 1080]);

    $res = actingAsApi($this->user)->getJson("{$this->url}/{$id}")->assertOk()->json('data');
    expect($res['check']['notes'][0])->toContain('metadata removed')
        ->and($res['media_url'])->toStartWith('/api/v1/media/creatives/');
    resetAuth();
    $this->get($res['media_url'])->assertOk()->assertHeader('Content-Type', 'image/webp');
});

it('fails an image of the wrong shape or too small, with a clear reason', function () {
    $id = upload(UploadedFile::fake()->image('square.png', 1200, 1200))->assertCreated()->json('data.id');
    expect(Creative::where('uuid', $id)->first())->status->value->toBe('failed')
        ->check_result->toMatchArray(['errors' => ['Your file is 1200×1200, a different shape from 1920×1080. Crop it to the same shape and upload again.']]);

    $id = upload(UploadedFile::fake()->image('tiny.png', 640, 360))->json('data.id');
    expect(Creative::where('uuid', $id)->first()->check_result['errors'][0])->toContain('too small');
});

it('needs the content declaration on every upload', function () {
    upload(UploadedFile::fake()->image('ad.jpg', 1920, 1080), ['declaration' => '0'])
        ->assertUnprocessable()->assertJsonValidationErrors('declaration');
});

it('refuses disguised files and unknown resolutions', function () {
    $path = tempnam(sys_get_temp_dir(), 'ad');
    file_put_contents($path, '<script>alert(1)</script>');
    upload(new UploadedFile($path, 'ad.mp4', 'video/mp4', null, true))->assertJsonValidationErrors('file');
    upload(UploadedFile::fake()->image('ad.jpg', 1000, 1000), ['resolution' => '1000x1000'])->assertJsonValidationErrors('resolution');
});

it('re-encodes a real video and strips its sound', function () {
    // CI and the Docker image have FFmpeg; a bare dev machine may not.
    if (! Process::run(['ffmpeg', '-version'])->successful()) {
        $this->markTestSkipped('FFmpeg is not installed.');
    }

    $src = tempnam(sys_get_temp_dir(), 'vid').'.mp4';
    Process::run(['ffmpeg', '-y', '-v', 'error', '-f', 'lavfi', '-i', 'testsrc=size=1280x720:rate=25:duration=3', '-f', 'lavfi', '-i', 'sine=duration=3',
        '-shortest', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-metadata', 'title=secret', $src])->throw();

    $id = upload(new UploadedFile($src, 'promo.mp4', 'video/mp4', null, true))->assertCreated()->json('data.id');
    $ad = Creative::where('uuid', $id)->first();
    expect($ad->status->value)->toBe('in_review')->and($ad->kind)->toBe('video')->and($ad->duration_s)->toEqualWithDelta(3, 0.2);

    $probe = json_decode(Process::run(['ffprobe', '-v', 'error', '-print_format', 'json', '-show_streams', '-show_format',
        Storage::disk('local')->path($ad->processed_path)])->output(), true);
    expect(collect($probe['streams'])->pluck('codec_type')->all())->toBe(['video'])
        ->and($probe['streams'][0]['width'])->toBe(1920)
        ->and($probe['format']['tags']['title'] ?? null)->toBeNull();
});

it('lets the review team approve an ad only after the advertiser\'s identity is verified', function () {
    $unverified = acceptTerms(User::factory()->create());
    $ad = Creative::factory()->inReview()->create(['organisation_id' => $this->org, 'uploaded_by' => $unverified]);

    actingAsApi($this->admin, mfa: true)->postJson("/api/v1/admin/creatives/{$ad->uuid}/approve")
        ->assertConflict()->assertJsonPath('message', "Verify the advertiser's identity document first.");

    $ad->update(['uploaded_by' => $this->user->id]);
    actingAsApi($this->admin, mfa: true)->postJson("/api/v1/admin/creatives/{$ad->uuid}/approve", ['note' => 'Clear and fine.'])
        ->assertOk()->assertJsonPath('data.status', 'approved');
});

it('schedules waiting bookings on approval, and refunds them when the ad is refused', function () {
    fundWallet($this->org, 1000);
    $screen = Screen::factory()->create();
    $ad = Creative::factory()->inReview()->create(['organisation_id' => $this->org, 'uploaded_by' => $this->user]);
    $day = now('Asia/Kathmandu')->addDays(2)->toDateString();
    $book = fn ($h) => actingAsApi($this->user)->postJson("/api/v1/orgs/{$this->org->uuid}/bookings", [
        'screen_id' => $screen->uuid, 'creative_id' => $ad->uuid, 'start_date' => $day, 'end_date' => $day, 'start_hour' => $h, 'end_hour' => $h + 1,
    ])->assertCreated()->assertJsonPath('data.status', 'checking');
    $book(8);
    $book(9);
    expect(Wallet::first()->reserved_paisa)->toBe(2 * 30 * 200);

    $reasons = actingAsApi($this->admin, mfa: true)->getJson('/api/v1/admin/creatives')->assertOk()->json('meta.reasons');
    expect(collect($reasons)->firstWhere('value', 'sexual')['label'])->toBe('Not permitted: sexual or pornographic content');

    actingAsApi($this->admin, mfa: true)->postJson("/api/v1/admin/creatives/{$ad->uuid}/reject", ['reason' => 'weapons', 'note' => 'Shows a pistol.'])
        ->assertOk()->assertJsonPath('data.reject_label', 'Not permitted: weapons or illegal goods');

    expect(Booking::pluck('status')->map->value->unique()->all())->toBe(['rejected'])
        ->and(Wallet::first()->reserved_paisa)->toBe(0)
        ->and(Wallet::first()->balance_paisa)->toBe(100000);

    actingAsApi($this->user)->getJson("{$this->url}/{$ad->uuid}")
        ->assertJsonPath('data.reject_label', 'Not permitted: weapons or illegal goods')
        ->assertJsonPath('data.review_note', 'Shows a pistol.');
});

it('keeps booked ads from being deleted', function () {
    fundWallet($this->org, 1000);
    $ad = Creative::factory()->create(['organisation_id' => $this->org, 'original_path' => 'creatives/x/y/original.png']);
    $day = now('Asia/Kathmandu')->addDays(2)->toDateString();
    actingAsApi($this->user)->postJson("/api/v1/orgs/{$this->org->uuid}/bookings", [
        'screen_id' => Screen::factory()->create()->uuid, 'creative_id' => $ad->uuid, 'start_date' => $day, 'end_date' => $day, 'start_hour' => 8, 'end_hour' => 9,
    ])->assertCreated();

    actingAsApi($this->user)->deleteJson("{$this->url}/{$ad->uuid}")->assertConflict();
});
