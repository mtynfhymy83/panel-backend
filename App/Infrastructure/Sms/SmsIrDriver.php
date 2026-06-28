<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms;

use App\Shared\Exceptions\BadRequestException;

class SmsIrDriver implements SmsServiceInterface
{
    private const VERIFY_URL = 'https://api.sms.ir/v1/send/verify';

    private string $apiKey;
    private int $templateId;
    private string $parameterName;

    public function __construct()
    {
        $this->apiKey = (string) ($_ENV['SMS_IR_API_KEY'] ?? '');
        $this->templateId = (int) ($_ENV['SMS_IR_TEMPLATE_ID'] ?? 0);
        $this->parameterName = (string) ($_ENV['SMS_IR_OTP_PARAMETER'] ?? 'CODE');
    }

    public function sendOtp(string $phone, string $code, string $purpose): bool
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('SMS_IR_API_KEY is not configured.');
        }
        if ($this->templateId <= 0) {
            throw new \RuntimeException('SMS_IR_TEMPLATE_ID is not configured.');
        }

        $payload = json_encode([
            'mobile'     => $this->normalizePhone($phone),
            'templateId' => $this->templateId,
            'parameters' => [
                ['name' => $this->parameterName, 'value' => $code],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->post($payload);
        $decoded = json_decode($response['body'], true);

        if ($response['http_code'] >= 200 && $response['http_code'] < 300 && ($decoded['status'] ?? 0) === 1) {
            return true;
        }

        $message = is_array($decoded) ? (string) ($decoded['message'] ?? 'SMS send failed') : 'SMS send failed';
        error_log("[SMS.ir] purpose={$purpose} phone={$phone} error={$message} body={$response['body']}");

        throw new BadRequestException('Unable to send verification code. Please try again later.');
    }

    private function post(string $payload): array
    {
        $ch = curl_init(self::VERIFY_URL);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize SMS HTTP client.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'x-api-key: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            error_log('[SMS.ir] curl error: ' . $error);
            throw new BadRequestException('Unable to send verification code. Please try again later.');
        }

        return ['http_code' => $httpCode, 'body' => (string) $body];
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? $phone;

        if (str_starts_with($phone, '98') && strlen($phone) === 12) {
            return '0' . substr($phone, 2);
        }

        if (str_starts_with($phone, '9') && strlen($phone) === 10) {
            return '0' . $phone;
        }

        return $phone;
    }
}
