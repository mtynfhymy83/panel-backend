<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\AuthService;
use App\Shared\Services\AuthContext;
use Swoole\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth)
    {
    }

    public function registerOtp(array $request): array
    {
        return $this->ok($this->auth->sendRegisterOtp($request));
    }

    public function registerVerify(array $request): array
    {
        return $this->created($this->auth->verifyRegister($request));
    }

    public function login(array $request): array
    {
        return $this->ok($this->auth->login($request));
    }

    public function passwordLoginOtp(array $request): array
    {
        return $this->ok($this->auth->sendPasswordLoginOtp($request));
    }

    public function passwordLoginVerify(array $request): array
    {
        return $this->ok($this->auth->verifyPasswordLogin($request));
    }

    public function logout(Request $request): array
    {
        return $this->ok($this->auth->logout($this->extractToken($request)));
    }

    public function me(): array
    {
        return $this->ok($this->auth->me(AuthContext::requireUserId(), AuthContext::activeRole()));
    }

    public function updateProfile(array $request): array
    {
        return $this->updated($this->auth->updateProfile(AuthContext::requireUserId(), $request));
    }

    public function changePassword(array $request): array
    {
        return $this->ok($this->auth->changePassword(AuthContext::requireUserId(), $request));
    }

    public function switchActiveRole(array $request): array
    {
        return $this->ok($this->auth->switchActiveRole(
            AuthContext::requireUserId(),
            $request,
            AuthContext::token()
        ));
    }

    public function phoneOtp(array $request): array
    {
        return $this->ok($this->auth->sendPhoneChangeOtp(AuthContext::requireUserId(), $request));
    }

    public function phoneVerify(array $request): array
    {
        return $this->updated($this->auth->verifyPhoneChange(AuthContext::requireUserId(), $request));
    }
}
