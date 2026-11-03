<?php

namespace App\Services\Sms;

/** Sends a text message to a Nepali mobile number (+977…). */
interface SmsSender
{
    public function send(string $phone, string $message): void;
}
