<?php

namespace App\Services;

use App\Exceptions\DomainError;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Cache;

/**
 * One-time 6-digit SMS codes for signing in with a phone number.
 * Only an HMAC of the code is stored; it expires, allows a few tries, and is used once.
 */
class PhoneOtpService
{
    public function __construct(private SmsSender $sms) {}

    /** @return array{expires_in: int, resend_in: int, dev_code?: string} */
    public function send(string $phone): array
    {
        $resendAt = Cache::get($this->coolKey($phone));
        if ($resendAt && $resendAt > time()) {
            $wait = $resendAt - time();
            throw new DomainError("Please wait {$wait} seconds before asking for a new code.", 429, ['phone' => ["Wait {$wait} s for a new code."]]);
        }

        $ttl = (int) config('dooh.otp.ttl_seconds');
        $resend = (int) config('dooh.otp.resend_seconds');
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->key($phone), ['hash' => $this->hash($phone, $code), 'attempts' => 0, 'expires' => time() + $ttl], $ttl);
        Cache::put($this->coolKey($phone), time() + $resend, $resend);

        $this->sms->send($phone, "Your Screen Ads code is {$code}. It expires in ".intdiv($ttl, 60).' minutes. Never share it with anyone.');

        $out = ['expires_in' => $ttl, 'resend_in' => $resend];
        if (config('dooh.otp.expose_code')) {
            $out['dev_code'] = $code;
        }

        return $out;
    }

    /** Check a code. With $consume (the default) a correct code can't be used again. */
    public function verify(string $phone, string $code, bool $consume = true): void
    {
        $entry = Cache::get($this->key($phone));
        if (! $entry || $entry['expires'] <= time()) {
            throw new DomainError('This code has expired. Ask for a new one.', 422, ['code' => ['Code expired. Ask for a new one.']]);
        }

        if (! hash_equals($entry['hash'], $this->hash($phone, $code))) {
            $entry['attempts']++;
            if ($entry['attempts'] >= (int) config('dooh.otp.max_attempts')) {
                Cache::forget($this->key($phone));
                throw new DomainError('Too many wrong codes. Ask for a new one.', 422, ['code' => ['Too many tries. Ask for a new code.']]);
            }
            Cache::put($this->key($phone), $entry, $entry['expires'] - time());
            throw new DomainError('That code is not right.', 422, ['code' => ['Wrong code. Check the SMS and try again.']]);
        }

        if ($consume) {
            Cache::forget($this->key($phone));
        }
    }

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone.'|'.$code, (string) config('app.key'));
    }

    private function key(string $phone): string
    {
        return 'otp:'.hash('sha256', $phone);
    }

    private function coolKey(string $phone): string
    {
        return 'otp:cool:'.hash('sha256', $phone);
    }
}
