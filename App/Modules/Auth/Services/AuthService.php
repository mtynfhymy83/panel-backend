<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Infrastructure\Cache\CacheInterface;
use App\Infrastructure\Database\DB;
use App\Shared\Enums\OtpPurpose;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\AuthenticationException;
use App\Shared\Exceptions\BadRequestException;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Http\ResourceTransformer;
use App\Shared\Repositories\UserRepository;
use App\Shared\Services\JwtService;
use App\Shared\Services\OtpService;
use App\Shared\Validators\Validator;

class AuthService
{
    public function __construct(
        private UserRepository $users,
        private OtpService $otp,
        private JwtService $jwt,
        private CacheInterface $cache
    ) {
    }

    public function sendRegisterOtp(array $input): array
    {
        Validator::make($input)
            ->required('firstName')
            ->required('lastName')
            ->required('phone')
            ->required('password')
            ->iranPhone('phone')
            ->minLength('password', 6)
            ->validate();

        $phone = (string) $input['phone'];

        if ($this->users->phoneExists($phone)) {
            throw new ValidationException(['phone' => 'Phone is already registered.']);
        }

        $this->cache->set('register_payload:' . $phone, [
            'first_name' => trim((string) $input['firstName']),
            'last_name'  => trim((string) $input['lastName']),
            'password'   => password_hash((string) $input['password'], PASSWORD_BCRYPT),
        ], 300);

        return $this->otp->send($phone, OtpPurpose::Register);
    }

    public function verifyRegister(array $input): array
    {
        Validator::make($input)->required('phone')->required('code')->iranPhone('phone')->validate();

        $phone = (string) $input['phone'];
        $this->otp->verify($phone, OtpPurpose::Register, (string) $input['code']);

        $payload = $this->cache->get('register_payload:' . $phone);
        if (!is_array($payload)) {
            throw new BadRequestException('Registration session expired. Please start again.');
        }

        return DB::transaction(function () use ($phone, $payload) {
            if ($this->users->phoneExists($phone)) {
                throw new ValidationException(['phone' => 'Phone is already registered.']);
            }

            $userId = $this->users->create(
                (string) $payload['first_name'],
                (string) $payload['last_name'],
                $phone,
                (string) $payload['password']
            );
            $this->users->addRole($userId, Role::Student->value);
            $this->cache->delete('register_payload:' . $phone);

            return $this->authResponse($userId, Role::Student->value);
        });
    }

    public function login(array $input): array
    {
        Validator::make($input)
            ->required('phone')
            ->required('password')
            ->iranPhone('phone')
            ->validate();

        $phone = (string) $input['phone'];
        $user = $this->users->findActiveByPhone($phone);
        if ($user === null || !password_verify((string) $input['password'], (string) $user['password'])) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $roles = $this->users->getRoles((int) $user['id']);
        if ($roles === []) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return $this->authResponse((int) $user['id'], $roles[0]);
    }

    public function sendPasswordLoginOtp(array $input): array
    {
        Validator::make($input)->required('phone')->iranPhone('phone')->validate();
        $phone = (string) $input['phone'];

        $user = $this->users->findActiveByPhone($phone);
        if ($user === null) {
            return ['message' => 'If the phone is registered, a verification code will be sent.'];
        }

        return $this->otp->send(
            $phone,
            OtpPurpose::PasswordLogin,
            (int) $user['id'],
            'If the phone is registered, a verification code will be sent.'
        );
    }

    public function verifyPasswordLogin(array $input): array
    {
        Validator::make($input)->required('phone')->required('code')->iranPhone('phone')->validate();

        $phone = (string) $input['phone'];
        $userId = $this->otp->verify($phone, OtpPurpose::PasswordLogin, (string) $input['code']);
        if ($userId === null) {
            $user = $this->users->findActiveByPhone($phone);
            $userId = $user ? (int) $user['id'] : 0;
        }
        if ($userId <= 0) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $user = $this->users->findById($userId);
        if ($user === null || !empty($user['deleted_at'])) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $roles = $this->users->getRoles($userId);
        return $this->authResponse($userId, $roles[0] ?? Role::Student->value);
    }

    public function logout(?string $token): array
    {
        if ($token !== null && $token !== '') {
            $this->cache->set('jwt_blacklist:' . hash('sha256', $token), true, $this->jwt->ttlSeconds());
        }
        return ['message' => 'Logged out.'];
    }

    public function me(int $userId, ?string $activeRole = null): array
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new AuthenticationException();
        }
        $roles = $this->users->getRoles($userId);
        $role = $activeRole ?? ($roles[0] ?? null);
        return ResourceTransformer::user($user, $role, $roles);
    }

    public function updateProfile(int $userId, array $input): array
    {
        Validator::make($input)
            ->required('firstName')
            ->required('lastName')
            ->string('firstName', 100)
            ->string('lastName', 100)
            ->validate();

        $this->users->updateProfile($userId, [
            'first_name' => trim((string) $input['firstName']),
            'last_name'  => trim((string) $input['lastName']),
        ]);

        return $this->me($userId);
    }

    public function changePassword(int $userId, array $input): array
    {
        Validator::make($input)->required('currentPassword')->required('newPassword')->minLength('newPassword', 6)->validate();

        $user = $this->users->findById($userId);
        if ($user === null || !password_verify((string) $input['currentPassword'], (string) $user['password'])) {
            throw new ValidationException(['currentPassword' => 'Current password is incorrect.']);
        }

        $this->users->updatePassword($userId, password_hash((string) $input['newPassword'], PASSWORD_BCRYPT));
        return ['message' => 'Password updated.'];
    }

    public function switchActiveRole(int $userId, array $input, ?string $currentToken = null): array
    {
        Validator::make($input)->required('role')->validate();
        $role = (string) $input['role'];
        if (!$this->users->hasRole($userId, $role)) {
            throw new ValidationException(['role' => 'Role is not assigned to this user.']);
        }

        if ($currentToken !== null && $currentToken !== '') {
            $this->cache->set('jwt_blacklist:' . hash('sha256', $currentToken), true, $this->jwt->ttlSeconds());
        }

        return $this->authResponse($userId, $role);
    }

    public function sendPhoneChangeOtp(int $userId, array $input): array
    {
        Validator::make($input)->required('phone')->iranPhone('phone')->validate();
        $phone = (string) $input['phone'];

        if ($this->users->phoneExists($phone)) {
            throw new ValidationException(['phone' => 'Phone is already registered.']);
        }

        return $this->otp->send($phone, OtpPurpose::PhoneChange, $userId);
    }

    public function verifyPhoneChange(int $userId, array $input): array
    {
        Validator::make($input)->required('phone')->required('code')->iranPhone('phone')->validate();

        $phone = (string) $input['phone'];
        $verifiedUserId = $this->otp->verify($phone, OtpPurpose::PhoneChange, (string) $input['code']);
        if ($verifiedUserId !== null && $verifiedUserId !== $userId) {
            throw new BadRequestException('Invalid verification session.');
        }

        if ($this->users->phoneExists($phone)) {
            throw new ValidationException(['phone' => 'Phone is already registered.']);
        }

        DB::transaction(function () use ($userId, $phone) {
            $this->users->updatePhone($userId, $phone);
        });

        return $this->me($userId);
    }

    private function authResponse(int $userId, string $activeRole): array
    {
        $user = $this->users->findById($userId);
        $roles = $this->users->getRoles($userId);
        $token = $this->jwt->issue($userId, $roles, $activeRole);

        return [
            'token' => $token,
            'user'  => ResourceTransformer::user($user, $activeRole, $roles),
        ];
    }
}
