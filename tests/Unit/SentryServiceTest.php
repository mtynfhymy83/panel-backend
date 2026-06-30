<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Monitoring\SentryService;
use App\Shared\Exceptions\AuthenticationException;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class SentryServiceTest extends TestCase
{
    public function testShouldNotReportExpectedHttpExceptions(): void
    {
        $this->assertFalse(SentryService::shouldReport(new ValidationException(['x' => 'y'])));
        $this->assertFalse(SentryService::shouldReport(new AuthenticationException()));
        $this->assertFalse(SentryService::shouldReport(new NotFoundException()));
    }

    public function testShouldReportUnexpectedExceptions(): void
    {
        $this->assertTrue(SentryService::shouldReport(new \RuntimeException('boom')));
    }
}
