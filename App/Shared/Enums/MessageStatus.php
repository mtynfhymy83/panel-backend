<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum MessageStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Replied = 'replied';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $s) => $s->value, self::cases());
    }
}
