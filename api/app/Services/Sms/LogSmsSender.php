<?php

namespace App\Services\Sms;

use App\Support\NepaliPhone;
use Illuminate\Support\Facades\Log;

/** Lab driver: writes the message to the log instead of sending it. Replace with an SMS gateway for real use. */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS to '.NepaliPhone::mask($phone).': '.$message);
    }
}
