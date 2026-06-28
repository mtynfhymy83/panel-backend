<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

class MailFactory
{
    public static function create(): MailServiceInterface
    {
        $driver = strtolower((string) ($_ENV['MAIL_DRIVER'] ?? 'log'));

        return match ($driver) {
            'smtp' => new SmtpDriver(),
            default => new LogDriver(),
        };
    }
}
