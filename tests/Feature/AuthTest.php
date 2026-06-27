<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Cache\FileDriver;
use App\Infrastructure\Sms\FakeDriver;
use App\Modules\Auth\Services\AuthService;
use App\Shared\Enums\OtpPurpose;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Repositories\UserRepository;
use App\Shared\Services\JwtService;
use App\Shared\Services\OtpService;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private function authService(): AuthService
    {
        $cache = new FileDriver(sys_get_temp_dir() . '/pardis-test-cache-' . getmypid());
        return new AuthService(
            new UserRepository(),
            new OtpService($cache, new FakeDriver()),
            new JwtService(),
            $cache
        );
    }

    public function testRegisterOtpRejectsInvalidPhone(): void
    {
        $this->expectException(ValidationException::class);
        $this->authService()->sendRegisterOtp([
            'fullName' => 'Ali',
            'username' => 'ali1',
            'phone'    => '12345',
            'password' => 'secret123',
        ]);
    }

    public function testRegisterVerifyCreatesStudentUser(): void
    {
        $cache = new FileDriver(sys_get_temp_dir() . '/pardis-test-cache-' . getmypid());
        $otp = new OtpService($cache, new FakeDriver());
        $auth = new AuthService(new UserRepository(), $otp, new JwtService(), $cache);

        $phone = '09121111111';
        $auth->sendRegisterOtp([
            'fullName' => 'Student One',
            'username' => 'student1',
            'phone'    => $phone,
            'password' => 'secret123',
        ]);

        $code = $this->extractOtpFromCache($cache, $phone, OtpPurpose::Register);
        $result = $auth->verifyRegister(['phone' => $phone, 'code' => $code]);

        $this->assertArrayHasKey('token', $result);
        $this->assertSame('student', $result['user']['role']);
        $this->assertContains('student', $result['user']['roles']);
    }

    public function testLoginWithValidCredentials(): void
    {
        $this->createUser('teacher1', Role::Teacher->value, '09122222222');
        $result = $this->authService()->login(['username' => 'teacher1', 'password' => 'secret123']);
        $this->assertArrayHasKey('token', $result);
        $this->assertSame('teacher', $result['user']['role']);
    }

    public function testUsernameUniquenessIncludesSoftDeleted(): void
    {
        $users = new UserRepository();
        $id = $users->create('Del User', 'deleted_user', '09123333333', password_hash('x', PASSWORD_BCRYPT));
        $users->addRole($id, Role::Student->value);
        $users->softDelete($id);

        $this->assertTrue($users->usernameExists('deleted_user'));
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
