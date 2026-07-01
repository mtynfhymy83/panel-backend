<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum MessageType: string
{
    case General = 'general';
    case Complaint = 'complaint';
    case Suggestion = 'suggestion';
    case Request = 'request';
    case Question = 'question';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $t) => $t->value, self::cases());
    }
}
