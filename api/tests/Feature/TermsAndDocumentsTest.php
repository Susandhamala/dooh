<?php

use App\Models\IdentityDocument;
use App\Models\Organisation;
use App\Models\TermsAcceptance;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('publishes the terms with the prohibited content and the legal warning', function () {
    $this->getJson('/api/v1/legal/terms')
        ->assertOk()
        ->assertJsonPath('data.version', config('terms.version'))
        ->assertJsonPath('data.prohibited.0.title', 'Sexual content and pornography')
        ->assertJsonStructure(['data' => ['intro', 'prohibited', 'rules', 'legal', 'declaration', 'sha256']]);
});

it('records a digital acceptance with version, text hash, time and IP', function () {
    $user = User::factory()->create();

    actingAsApi($user)->postJson('/api/v1/auth/terms/accept', ['version' => config('terms.version')])
        ->assertUnprocessable()->assertJsonValidationErrors('agree');

    actingAsApi($user)->postJson('/api/v1/auth/terms/accept', ['version' => 'old', 'agree' => true])->assertConflict();

    actingAsApi($user)->postJson('/api/v1/auth/terms/accept', ['version' => config('terms.version'), 'agree' => true])->assertOk();

    $row = TermsAcceptance::where('user_id', $user->id)->first();
    expect($row->version)->toBe(config('terms.version'))
        ->and($row->text_sha256)->toBe($this->getJson('/api/v1/legal/terms')->json('data.sha256'))
        ->and($row->ip_address)->toBe('127.0.0.1');

    actingAsApi($user)->getJson('/api/v1/auth/me')->assertJsonPath('data.terms_accepted', true);
});

it('blocks loading money, uploading and documents until the terms are accepted', function () {
    $user = User::factory()->create();
    $org = Organisation::factory()->withMember($user)->create();

    actingAsApi($user)->postJson("/api/v1/orgs/{$org->uuid}/wallet/topup", ['amount' => 500, 'gateway' => 'esewa'])
        ->assertForbidden()->assertJsonPath('errors.terms.0', config('terms.version'));
    actingAsApi($user)->postJson('/api/v1/me/documents', [])->assertForbidden();
    actingAsApi($user)->postJson("/api/v1/orgs/{$org->uuid}/creatives", [])->assertForbidden();
});

it('stores identity documents encrypted and shows only the last digits', function () {
    Storage::fake('local');
    $user = acceptTerms(User::factory()->create());
    $file = UploadedFile::fake()->image('citizenship.jpg', 800, 500);

    actingAsApi($user)->postJson('/api/v1/me/documents', ['type' => 'citizenship', 'number' => '27-01-75-01234', 'file' => $file])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.number', '••••••••••1234')
        ->assertJsonMissingPath('data.file_url');

    $doc = IdentityDocument::first();
    $stored = Storage::disk('local')->get($doc->file_path);
    expect($stored)->not->toContain(substr((string) file_get_contents($file->getRealPath()), 0, 64))
        ->and($doc->getRawOriginal('number'))->not->toContain('01234');

    // One document at a time while it is being checked.
    actingAsApi($user)->postJson('/api/v1/me/documents', ['type' => 'passport', 'number' => 'PA123456', 'file' => UploadedFile::fake()->image('p.jpg')])
        ->assertConflict();
});

it('refuses files that are not images or PDFs, whatever their name says', function () {
    $user = acceptTerms(User::factory()->create());
    // A real file, so its type is read from the bytes (fake test files take it from the name).
    $path = tempnam(sys_get_temp_dir(), 'doc');
    file_put_contents($path, "<?php system(\$_GET['c']);");
    $fake = new UploadedFile($path, 'id.jpg', 'image/jpeg', null, true);

    actingAsApi($user)->postJson('/api/v1/me/documents', ['type' => 'citizenship', 'number' => '12345', 'file' => $fake])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('lets the review team see the file once, then deletes it after the decision', function () {
    Storage::fake('local');
    $user = acceptTerms(User::factory()->create());
    actingAsApi($user)->postJson('/api/v1/me/documents', ['type' => 'pan_vat', 'number' => '601234567', 'file' => UploadedFile::fake()->image('pan.png')]);
    $admin = User::factory()->withMfa()->create()->assignRole('admin');

    $item = actingAsApi($admin, mfa: true)->getJson('/api/v1/admin/documents')->assertOk()->json('data.0');
    expect($item['user']['id'])->toBe($user->uuid)->and($item['file_url'])->toStartWith('/api/v1/media/documents/');

    resetAuth();
    $this->get($item['file_url'])->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
    $this->get(strtok($item['file_url'], '?'))->assertForbidden(); // no signature, no file

    actingAsApi($admin, mfa: true)->postJson("/api/v1/admin/documents/{$item['id']}/reject")->assertUnprocessable();
    actingAsApi($admin, mfa: true)->postJson("/api/v1/admin/documents/{$item['id']}/approve")->assertOk()->assertJsonPath('data.file_url', null);

    expect(Storage::disk('local')->allFiles('documents'))->toBe([]);
    actingAsApi($user)->getJson('/api/v1/auth/me')->assertJsonPath('data.document_status', 'approved');
});
