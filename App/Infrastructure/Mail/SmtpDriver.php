<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

class SmtpDriver implements MailServiceInterface
{
    public function sendOtp(string $to, string $code, string $purpose): bool
    {
        $label = match ($purpose) {
            'register'        => 'ثبت‌نام',
            'password_login'  => 'ورود',
            'phone_change'    => 'تغییر شماره',
            default           => 'تأیید',
        };

        $subject = 'کد تأیید پنل پاردیس';
        $body = "کد تأیید {$label}: {$code}\n\nاین کد تا ۵ دقیقه معتبر است.\n\nپنل پاردیس";

        return $this->send($to, $subject, $body);
    }

    private function send(string $to, string $subject, string $body): bool
    {
        $host = trim((string) ($_ENV['MAIL_HOST'] ?? ''));
        if ($host === '') {
            throw new \RuntimeException('MAIL_HOST is not configured.');
        }

        $port = max(1, (int) ($_ENV['MAIL_PORT'] ?? 587));
        $username = (string) ($_ENV['MAIL_USERNAME'] ?? '');
        $password = (string) ($_ENV['MAIL_PASSWORD'] ?? '');
        $from = trim((string) ($_ENV['MAIL_FROM'] ?? $username));
        $fromName = trim((string) ($_ENV['MAIL_FROM_NAME'] ?? 'Pardis Panel'));
        $encryption = strtolower(trim((string) ($_ENV['MAIL_ENCRYPTION'] ?? 'tls')));

        $client = new SmtpClient($host, $port, $encryption, $username, $password);
        $client->sendMail($from, $fromName, $to, $subject, $body);

        return true;
    }
}
