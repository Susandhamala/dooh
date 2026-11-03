<?php

namespace App\Providers;

use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Support\NepaliPhone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Only the log driver exists so far; an SMS gateway (Sparrow, Aakash) plugs in here.
        $this->app->bind(SmsSender::class, fn () => match (config('dooh.sms.driver')) {
            default => new LogSmsSender,
        });
    }

    public function boot(): void
    {
        // Sign-in and two-factor endpoints: per IP, and per account (user, phone or email) when we know it.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('id:'.($request->user()?->getAuthIdentifier()
                ?? NepaliPhone::normalise((string) $request->input('phone'))
                ?? mb_strtolower((string) $request->input('email')))),
        ]);
    }
}
