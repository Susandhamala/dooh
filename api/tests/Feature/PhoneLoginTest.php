<?php

use App\Exceptions\DomainError;
use App\Models\User;
use App\Services\PhoneOtpService;
use App\Services\Sms\SmsSender;
use App\Support\NepaliPhone;

/** Captures texts instead of sending them. */
class FakeSms implements SmsSender
{
    public array $sent = [];

    public function send(string $phone, string $message): void
    {
        $this->sent[] = [$phone, $message];
    }

    public function lastCode(): string
    {
        preg_match('/\b(\d{6})\b/', end($this->sent)[1], $m);

        return $m[1];
    }
}

beforeEach(function () {
    $this->sms = new FakeSms;
    $this->app->instance(SmsSender::class, $this->sms);
});

it('accepts Nepali mobile numbers in the usual ways and stores them one way', function (string $input) {
    expect(NepaliPhone::normalise($input))->toBe('+9779812345678');
})->with(['9812345678', '+977 981-234-5678', '977 9812345678', '09812345678', ' 98 1234 5678 ']);

it('refuses numbers that are not Nepali mobiles', function (string $input) {
    $this->postJson('/api/v1/auth/phone/code', ['phone' => $input])
        ->assertUnprocessable()->assertJsonValidationErrors('phone');
})->with(['981234567', '98123456789', '0141234567', '+91 9812345678', '9512345678', 'abc']);

it('signs up a new number with a code and a name, and gives it an advertiser organisation', function () {
    $this->postJson('/api/v1/auth/phone/code', ['phone' => '9812345678'])->assertOk()->assertJsonPath('data.expires_in', 300);
    $code = $this->sms->lastCode();
    expect($this->sms->sent[0][0])->toBe('+9779812345678');

    // A real code but no name yet: asks for the name and keeps the code valid.
    $this->postJson('/api/v1/auth/phone/login', ['phone' => '9812345678', 'code' => $code])
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    $res = $this->postJson('/api/v1/auth/phone/login', ['phone' => '981-234-5678', 'code' => $code, 'name' => 'Sita Shrestha'])
        ->assertOk()
        ->assertJsonPath('data.user.phone', '+9779812345678')
        ->assertJsonPath('data.user.phone_verified', true)
        ->assertJsonPath('data.user.terms_accepted', false)
        ->assertJsonPath('data.user.document_status', 'none');

    $user = User::where('phone', '+9779812345678')->first();
    expect($user->organisations()->first()->type->value)->toBe('advertiser')
        ->and($user->hasRole('advertiser'))->toBeTrue()
        ->and($res->json('data.token'))->not->toBeEmpty();
});

it('uses a code once, and only for that number', function () {
    $this->postJson('/api/v1/auth/phone/code', ['phone' => '9801111111']);
    $code = $this->sms->lastCode();

    $this->postJson('/api/v1/auth/phone/login', ['phone' => '9802222222', 'code' => $code, 'name' => 'X'])->assertUnprocessable();
    $this->postJson('/api/v1/auth/phone/login', ['phone' => '9801111111', 'code' => $code, 'name' => 'Ram'])->assertOk();
    $this->postJson('/api/v1/auth/phone/login', ['phone' => '9801111111', 'code' => $code])->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Code expired. Ask for a new one.');
});

it('locks the code after five wrong tries', function () {
    $this->postJson('/api/v1/auth/phone/code', ['phone' => '9803333333']);
    $code = $this->sms->lastCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    // The per-number rate limit (5/min) answers 429 before this over HTTP, so try the codes directly.
    foreach (range(1, 5) as $_) {
        try {
            app(PhoneOtpService::class)->verify('+9779803333333', $wrong);
        } catch (DomainError) {
        }
    }
    $this->postJson('/api/v1/auth/phone/login', ['phone' => '9803333333', 'code' => $code, 'name' => 'Ram'])
        ->assertUnprocessable()->assertJsonPath('errors.code.0', 'Code expired. Ask for a new one.');
});

it('makes you wait before sending another code', function () {
    $this->postJson('/api/v1/auth/phone/code', ['phone' => '9804444444'])->assertOk();
    $this->postJson('/api/v1/auth/phone/code', ['phone' => '9804444444'])->assertStatus(429);
    expect($this->sms->sent)->toHaveCount(1);
});

it('signs in an existing number without asking for a name, and refuses suspended accounts', function () {
    $user = User::factory()->create(['phone' => '+9779805555555']);

    $this->postJson('/api/v1/auth/phone/code', ['phone' => '9805555555']);
    $this->postJson('/api/v1/auth/phone/login', ['phone' => '9805555555', 'code' => $this->sms->lastCode()])
        ->assertOk()->assertJsonPath('data.user.id', $user->uuid);

    $user->forceFill(['status' => 'suspended'])->save();
    $this->travel(2)->minutes();
    $this->postJson('/api/v1/auth/phone/code', ['phone' => '9805555555']);
    $this->postJson('/api/v1/auth/phone/login', ['phone' => '9805555555', 'code' => $this->sms->lastCode()])->assertForbidden();
});

it('never returns the code unless the lab switch is on', function () {
    config(['dooh.otp.expose_code' => false]);
    $this->postJson('/api/v1/auth/phone/code', ['phone' => '9806666666'])->assertOk()->assertJsonMissingPath('data.dev_code');
});
