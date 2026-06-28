<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

interface MailServiceInterface
{
    public function sendOtp(string $to, string $code, string $purpose): bool;
}
