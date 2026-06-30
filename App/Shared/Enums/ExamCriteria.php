<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum ExamCriteria: string
{
    case Phonics = 'phonics';
    case Receptive = 'receptive';
    case Vocabulary = 'vocabulary';

    public function label(): string
    {
        return match ($this) {
            self::Phonics    => 'فونیکس و تلفظ',
            self::Receptive  => 'درک و پاسخ',
            self::Vocabulary => 'واژگان',
        };
    }

    /** @return list<string> */
    public static function labels(): array
    {
        return array_map(static fn (self $c) => $c->label(), self::cases());
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
