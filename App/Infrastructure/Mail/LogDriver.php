<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

class LogDriver implements MailServiceInterface
{
    public function sendOtp(string $to, string $code, string $purpose): bool
    {
        error_log("[Mail Log] purpose={$purpose} to={$to} code={$code}");
        return true;
    }
}
