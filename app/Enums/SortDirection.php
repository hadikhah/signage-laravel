<?php

namespace App\Enums;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    public static function fromInput(?string $input): self
    {
        return strtolower((string) $input) === 'desc' ? self::Desc : self::Asc;
    }
}
