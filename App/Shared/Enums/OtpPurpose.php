<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum OtpPurpose: string
{
    case Register = 'register';
    case PasswordLogin = 'password_login';
    case PhoneChange = 'phone_change';
}
