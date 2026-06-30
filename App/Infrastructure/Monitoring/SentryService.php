<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use App\Framework\Bootstrap\EnvironmentManager;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Exceptions\AuthenticationException;
use App\Shared\Exceptions\BadRequestException;
use App\Shared\Exceptions\ConflictException;
use App\Shared\Exceptions\ForbiddenException;
use App\Shared\Exceptions\HttpException;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Exceptions\ValidationException;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Swoole\Http\Request;
use Throwable;

use function Sentry\captureException;
use function Sentry\configureScope;
use function Sentry\init;

/**
 * Sentry Cloud integration for long-running Swoole workers.
 * Initialize once per worker; flush after each captured event.
 */
final class SentryService
{
    private static bool $initialized = false;

    /** @var list<class-string<Throwable>> */
    private const IGNORED_EXCEPTIONS = [
        ValidationException::class,
        AuthenticationException::class,
        AccessDeniedException::class,
        ForbiddenException::class,
        NotFoundException::class,
        BadRequestException::class,
        ConflictException::class,
    ];

    public static function init(): void
    {
        if (self::$initialized || !self::shouldEnable()) {
            return;
        }

        $options = [
            'dsn'                => (string) EnvironmentManager::get('SENTRY_DSN'),
            'environment'        => (string) EnvironmentManager::get('SENTRY_ENVIRONMENT', 'production'),
            'traces_sample_rate' => self::sampleRate('SENTRY_TRACES_SAMPLE_RATE', 0.0),
        ];

        $release = trim((string) EnvironmentManager::get('SENTRY_RELEASE', ''));
        if ($release !== '') {
            $options['release'] = $release;
        }

        init($options);
        self::$initialized = true;
    }

    public static function isEnabled(): bool
    {
        return self::$initialized;
    }

    public static function configureRequestScope(Request $request): void
    {
        if (!self::$initialized) {
            return;
        }

        $method = (string) ($request->server['request_method'] ?? 'GET');
        $uri = (string) ($request->server['request_uri'] ?? '/');
        $query = is_array($request->get ?? null) ? $request->get : [];
        $clientIp = (string) ($request->server['remote_addr'] ?? '');

        configureScope(static function (Scope $scope) use ($method, $uri, $query, $clientIp, $request): void {
            $scope->setTag('http.method', $method);
            $scope->setTag('http.route', explode('?', $uri, 2)[0]);

            if ($clientIp !== '') {
                $scope->setUser(['ip_address' => $clientIp]);
            }

            $headers = self::sanitizeHeaders($request->header ?? []);
            $scope->setContext('request', [
                'method'       => $method,
                'url'          => $uri,
                'query_string' => http_build_query($query),
                'headers'      => $headers,
            ]);
        });
    }

    public static function report(Throwable $e): void
    {
        if (!self::$initialized || !self::shouldReport($e)) {
            return;
        }

        captureException($e);
        self::flush();
    }

    public static function shouldReport(Throwable $e): bool
    {
        foreach (self::IGNORED_EXCEPTIONS as $ignored) {
            if ($e instanceof $ignored) {
                return false;
            }
        }

        if ($e instanceof HttpException && $e->getStatusCode() < 500) {
            return false;
        }

        return true;
    }

    private static function shouldEnable(): bool
    {
        if (!EnvironmentManager::getBool('SENTRY_ENABLED', true)) {
            return false;
        }

        return trim((string) EnvironmentManager::get('SENTRY_DSN', '')) !== '';
    }

    private static function sampleRate(string $key, float $default): float
    {
        $value = EnvironmentManager::get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        $rate = (float) $value;
        if ($rate < 0.0) {
            return 0.0;
        }
        if ($rate > 1.0) {
            return 1.0;
        }

        return $rate;
    }

    /** @param array<string, mixed> $headers */
    private static function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        $redact = ['authorization', 'cookie', 'token', 'x-api-key'];

        foreach ($headers as $key => $value) {
            $name = strtolower((string) $key);
            $sanitized[$name] = in_array($name, $redact, true) ? '[Filtered]' : (string) $value;
        }

        return $sanitized;
    }

    private static function flush(): void
    {
        $client = SentrySdk::getCurrentHub()->getClient();
        if ($client !== null) {
            $client->flush(2000);
        }
    }
}
