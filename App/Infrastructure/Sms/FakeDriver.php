<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms;

class FakeDriver implements SmsServiceInterface
{
    public function sendOtp(string $phone, string $code, string $purpose): bool
    {
        error_log("[SMS Fake] purpose={$purpose} phone={$phone} code={$code}");
        return true;
    }
}
