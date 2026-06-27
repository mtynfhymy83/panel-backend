<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum Level: string
{
    case L1 = '1';
    case L2 = '2';
    case L3 = '3';
    case L4 = '4';
    case L5 = '5';
    case L6 = '6';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $l) => $l->value, self::cases());
    }
}
