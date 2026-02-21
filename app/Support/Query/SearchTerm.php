<?php

namespace App\Support\Query;

final class SearchTerm
{
    public static function contains(string $value): string
    {
        return '%' . self::escapeLike($value) . '%';
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\%', '\_'],
            trim($value)
        );
    }
}
