<?php

declare(strict_types=1);

use App\Http\Routers\Router;
use App\Modules\Auth\Controllers\AuthController;

return static function (Router $router): void {
    $router->post('api', '/auth/register/otp', AuthController::class, 'registerOtp');
    $router->post('api', '/auth/register/verify', AuthController::class, 'registerVerify');
    $router->post('api', '/auth/login', AuthController::class, 'login');
    $router->post('api', '/auth/password-login/otp', AuthController::class, 'passwordLoginOtp');
    $router->post('api', '/auth/password-login/verify', AuthController::class, 'passwordLoginVerify');
    $router->post('api', '/auth/refresh', AuthController::class, 'refresh');
    $router->post('api', '/auth/logout', AuthController::class, 'logout', 'auth');

    $router->get('api', '/me', AuthController::class, 'me', 'auth');
    $router->patch('api', '/me', AuthController::class, 'updateProfile', 'auth');
    $router->patch('api', '/me/password', AuthController::class, 'changePassword', 'auth');
    $router->post('api', '/me/active-role', AuthController::class, 'switchActiveRole', 'auth');
    $router->post('api', '/me/phone/otp', AuthController::class, 'phoneOtp', 'auth');
    $router->patch('api', '/me/phone/verify', AuthController::class, 'phoneVerify', 'auth');
};
