<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Infrastructure\Cache\CacheInterface;
use App\Infrastructure\Mail\MailServiceInterface;
use App\Infrastructure\Sms\SmsServiceInterface;
use App\Shared\Enums\OtpPurpose;
use App\Shared\Exceptions\BadRequestException;
use App\Shared\Exceptions\ValidationException;

class OtpService
{
    private const OTP_TTL = 300;
    private const MAX_ATTEMPTS = 5;
    private const RATE_LIMIT = 3;
    private const RATE_WINDOW = 60;

    public function __construct(
        private CacheInterface $cache,
        private SmsServiceInterface $sms,
        private MailServiceInterface $mail
    ) {
    }

    public function send(
        string $phone,
        OtpPurpose $purpose,
        ?int $userId = null,
        string $message = 'Verification code sent.',
        ?string $email = null
    ): array {
        $this->assertRateLimit($phone, $purpose);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = password_hash($code, PASSWORD_BCRYPT);

        $this->dispatchOtp($phone, $email, $code, $purpose->value);

        $payload = [
            'hash'     => $hash,
            'attempts' => 0,
            'user_id'  => $userId,
        ];

        $this->cache->set($this->key($phone, $purpose), $payload, self::OTP_TTL);

        return $this->buildSentResponse($code, $message);
    }

    public function verify(string $phone, OtpPurpose $purpose, string $code): ?int
    {
        $key = $this->key($phone, $purpose);
        $payload = $this->cache->get($key);
        if (!is_array($payload) || !isset($payload['hash'])) {
            throw new ValidationException(['code' => 'Invalid or expired verification code.']);
        }

        $attempts = (int) ($payload['attempts'] ?? 0) + 1;
        if ($attempts > self::MAX_ATTEMPTS) {
            $this->cache->delete($key);
            throw new BadRequestException('Too many invalid attempts. Please request a new code.');
        }

        if (!password_verify($code, (string) $payload['hash'])) {
            $payload['attempts'] = $attempts;
            $this->cache->set($key, $payload, self::OTP_TTL);
            throw new ValidationException(['code' => 'Invalid or expired verification code.']);
        }

        $this->cache->delete($key);
        return isset($payload['user_id']) ? (int) $payload['user_id'] : null;
    }

    private function dispatchOtp(string $phone, ?string $email, string $code, string $purpose): void
    {
        $channel = $this->otpChannel();

        if ($channel === 'sms' || $channel === 'both') {
            $this->sms->sendOtp($phone, $code, $purpose);
        }

        if ($channel === 'email' || $channel === 'both') {
            $to = trim((string) $email);
            if ($to === '') {
                throw new BadRequestException('Email is required for verification code delivery.');
            }
            $this->mail->sendOtp($to, $code, $purpose);
        }
    }

    private function otpChannel(): string
    {
        $channel = strtolower(trim((string) ($_ENV['OTP_CHANNEL'] ?? 'sms')));

        return match ($channel) {
            'email', 'both' => $channel,
            default => 'sms',
        };
    }

    private function assertRateLimit(string $phone, OtpPurpose $purpose): void
    {
        $rateKey = 'otp_rate:' . $purpose->value . ':' . $phone;
        $count = $this->cache->increment($rateKey, self::RATE_WINDOW);
        if ($count > self::RATE_LIMIT) {
            throw new BadRequestException('Too many requests. Please try again later.');
        }
    }

    private function key(string $phone, OtpPurpose $purpose): string
    {
        return 'otp:' . $purpose->value . ':' . $phone;
    }

    private function buildSentResponse(string $code, string $message): array
    {
        $response = ['message' => $message];
        if ($this->shouldExposeDebugOtp()) {
            $response['debugOtp'] = $code;
        }

        return $response;
    }

    private function shouldExposeDebugOtp(): bool
    {
        if (($_ENV['OTP_DEBUG_RESPONSE'] ?? '0') === '1') {
            return true;
        }

        if (($_ENV['APP_DEBUG'] ?? '0') !== '1') {
            return false;
        }

        $channel = $this->otpChannel();

        if ($channel === 'sms' || $channel === 'both') {
            $smsDriver = strtolower((string) ($_ENV['SMS_DRIVER'] ?? 'fake'));
            if ($smsDriver === 'fake' || $smsDriver === '') {
                return true;
            }
        }

        if ($channel === 'email' || $channel === 'both') {
            $mailDriver = strtolower((string) ($_ENV['MAIL_DRIVER'] ?? 'log'));
            if ($mailDriver === 'log' || $mailDriver === 'fake' || $mailDriver === '') {
                return true;
            }
        }

        return false;
    }
}
