<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Student = 'student';
    case Examiner = 'examiner';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $r) => $r->value, self::cases());
    }
}
