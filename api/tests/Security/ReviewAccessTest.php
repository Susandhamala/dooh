<?php

/*
 * The review queues (ads and identity documents) and their files are for the platform's review
 * team only, with a two-factor session. Advertisers can never approve their own ads, and media
 * links only work while signed and unexpired.
 */

use App\Models\Creative;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->advertiser = verifyIdentity(acceptTerms(User::factory()->withMfa()->create()));
    $this->org = Organisation::factory()->withMember($this->advertiser)->create();
    $this->ad = Creative::factory()->inReview()->create(['organisation_id' => $this->org, 'uploaded_by' => $this->advertiser]);
});

dataset('review endpoints', [
    'list ads' => ['GET', '/admin/creatives'],
    'approve ad' => ['POST', '/admin/creatives/{ad}/approve'],
    'reject ad' => ['POST', '/admin/creatives/{ad}/reject'],
    'list documents' => ['GET', '/admin/documents'],
]);

it('keeps advertisers out of the review queues, even with two-factor', function (string $method, string $path) {
    actingAsApi($this->advertiser, mfa: true)
        ->json($method, '/api/v1'.str_replace('{ad}', $this->ad->uuid, $path), ['reason' => 'other'])
        ->assertForbidden();

    expect($this->ad->fresh()->status->value)->toBe('in_review');
})->with('review endpoints');

it('needs a two-factor session for reviewers too', function () {
    $admin = User::factory()->withMfa()->create()->assignRole('admin');

    actingAsApi($admin)->postJson("/api/v1/admin/creatives/{$this->ad->uuid}/approve")
        ->assertForbidden()->assertJsonPath('errors.mfa.0', 'verify');
});

it('serves ad files only through signed, unexpired links', function () {
    Storage::fake('local');
    $id = actingAsApi($this->advertiser)->post("/api/v1/orgs/{$this->org->uuid}/creatives", [
        'name' => 'Promo', 'category' => 'retail', 'resolution' => '1920x1080', 'declaration' => '1',
        'file' => UploadedFile::fake()->image('ad.png', 1920, 1080),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    $url = actingAsApi($this->advertiser)->getJson("/api/v1/orgs/{$this->org->uuid}/creatives/{$id}")->json('data.media_url');
    resetAuth();

    $this->get($url)->assertOk();
    $this->get("/api/v1/media/creatives/{$id}/media")->assertForbidden();
    $this->get(str_replace('media?', 'thumb?', $url))->assertForbidden(); // signature covers the variant
    $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), $url))->assertForbidden();

    $this->travel(31)->minutes();
    $this->get($url)->assertForbidden();
});
