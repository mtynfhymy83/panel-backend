<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms;

class SmsFactory
{
    public static function create(): SmsServiceInterface
    {
        $driver = strtolower((string) ($_ENV['SMS_DRIVER'] ?? 'fake'));

        return match ($driver) {
            'smsir', 'sms.ir' => new SmsIrDriver(),
            default           => new FakeDriver(),
        };
    }
}
