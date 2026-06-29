<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Cache\FileDriver;
use App\Infrastructure\Mail\LogDriver;
use App\Infrastructure\Sms\FakeDriver;
use App\Modules\Auth\Services\AuthService;
use App\Shared\Enums\OtpPurpose;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\AuthenticationException;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Repositories\RefreshTokenRepository;
use App\Shared\Repositories\UserRepository;
use App\Shared\Services\JwtService;
use App\Shared\Services\OtpService;
use App\Shared\Services\RefreshTokenService;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private function authService(): AuthService
    {
        $cache = new FileDriver(sys_get_temp_dir() . '/pardis-test-cache-' . getmypid());
        return new AuthService(
            new UserRepository(),
            new OtpService($cache, new FakeDriver(), new LogDriver()),
            new JwtService(),
            new RefreshTokenService(new RefreshTokenRepository()),
            $cache
        );
    }

    public function testRegisterOtpIncludesDebugOtpWhenFakeSmsAndDebug(): void
    {
        $result = $this->authService()->sendRegisterOtp([
            'firstName' => 'Ali',
            'lastName'  => 'Rezaei',
            'phone'     => '09124444444',
            'password'  => 'secret123',
        ]);

        $this->assertSame('Verification code sent to your phone.', $result['message']);
        $this->assertArrayHasKey('debugOtp', $result);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $result['debugOtp']);
    }

    public function testRegisterOtpRejectsInvalidPhone(): void
    {
        $this->expectException(ValidationException::class);
        $this->authService()->sendRegisterOtp([
            'firstName' => 'Ali',
            'lastName'  => 'Rezaei',
            'phone'     => '12345',
            'password'  => 'secret123',
        ]);
    }

    public function testRegisterVerifyCreatesStudentUser(): void
    {
        $cache = new FileDriver(sys_get_temp_dir() . '/pardis-test-cache-' . getmypid());
        $otp = new OtpService($cache, new FakeDriver(), new LogDriver());
        $auth = new AuthService(
            new UserRepository(),
            $otp,
            new JwtService(),
            new RefreshTokenService(new RefreshTokenRepository()),
            $cache
        );

        $phone = '09121111111';
        $auth->sendRegisterOtp([
            'firstName' => 'Student',
            'lastName'  => 'One',
            'phone'     => $phone,
            'password'  => 'secret123',
        ]);

        $code = $this->extractOtpFromCache($cache, $phone, OtpPurpose::Register);
        $result = $auth->verifyRegister(['phone' => $phone, 'code' => $code]);

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refreshToken', $result);
        $this->assertNotEmpty($result['refreshToken']);
        $this->assertSame('student', $result['user']['role']);
        $this->assertSame('09121111111', $result['user']['phone']);
        $this->assertNull($result['user']['email']);
    }

    public function testLoginWithValidCredentials(): void
    {
        $this->createUser(Role::Teacher->value, '09122222222', 'Teacher', 'One');
        $result = $this->authService()->login(['phone' => '09122222222', 'password' => 'secret123']);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('refreshToken', $result);
        $this->assertSame('teacher', $result['user']['role']);
    }

    public function testRefreshIssuesNewTokensAndRotatesRefreshToken(): void
    {
        $auth = $this->authService();
        $this->createUser(Role::Student->value, '09125555555');
        $login = $auth->login(['phone' => '09125555555', 'password' => 'secret123']);
        $oldRefresh = $login['refreshToken'];

        $refreshed = $auth->refresh(['refreshToken' => $oldRefresh]);

        $this->assertNotSame($login['token'], $refreshed['token']);
        $this->assertNotSame($oldRefresh, $refreshed['refreshToken']);
        $this->assertSame('student', $refreshed['user']['role']);

        $this->expectException(AuthenticationException::class);
        $auth->refresh(['refreshToken' => $oldRefresh]);
    }

    public function testRefreshRejectsRevokedToken(): void
    {
        $auth = $this->authService();
        $this->createUser(Role::Student->value, '09126666666');
        $login = $auth->login(['phone' => '09126666666', 'password' => 'secret123']);

        $auth->logout(null, $login['refreshToken']);

        $this->expectException(AuthenticationException::class);
        $auth->refresh(['refreshToken' => $login['refreshToken']]);
    }

    public function testPhoneUniquenessIncludesSoftDeleted(): void
    {
        $users = new UserRepository();
        $id = $users->create('Del', 'User', '09123333333', password_hash('x', PASSWORD_BCRYPT), 'del@example.com');
        $users->addRole($id, Role::Student->value);
        $users->softDelete($id);

        $this->assertTrue($users->phoneExists('09123333333'));
    }

    private function extractOtpFromCache(FileDriver $cache, string $phone, OtpPurpose $purpose): string
    {
        $payload = $cache->get('otp:' . $purpose->value . ':' . $phone);
        $this->assertIsArray($payload);
        for ($i = 0; $i <= 999999; $i++) {
            $code = str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            if (password_verify($code, (string) $payload['hash'])) {
                return $code;
            }
        }
        $this->fail('Could not extract OTP from cache');
    }
}
