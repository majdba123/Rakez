<?php

if (! function_exists('fake')) {
    /**
     * Get a shared Faker instance.
     *
     * This is intentionally defined as a global helper because multiple seeders
     * call fake() directly.
     */
    function fake(?string $locale = null)
    {
        if (!class_exists(\Faker\Factory::class)) {
            throw new \RuntimeException(
                'Faker is not installed. To run seeders, install dev dependencies (run `composer install` without `--no-dev`) '
                . 'or add `fakerphp/faker` to require and update composer.lock.'
            );
        }

        static $fakers = [];

        $locale = $locale ?: config('app.faker_locale', 'en_US');

        return $fakers[$locale] ??= \Faker\Factory::create($locale);
    }
}


