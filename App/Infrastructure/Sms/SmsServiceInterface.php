<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms;

interface SmsServiceInterface
{
    public function sendOtp(string $phone, string $code, string $purpose): bool;
}
