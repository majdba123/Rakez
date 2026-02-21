<?php

namespace App\Rules;

class CommonValidationRules
{
    /**
     * Standard name/title rule: required string, max 255.
     *
     * @return array<int, string>
     */
    public static function name(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Standard email rule: required, valid email.
     *
     * @return array<int, string>
     */
    public static function email(): array
    {
        return ['required', 'email'];
    }

    /**
     * Optional string, max 255.
     *
     * @return array<int, string>
     */
    public static function optionalStringMax255(): array
    {
        return ['nullable', 'string', 'max:255'];
    }
}
