<?php

use Faker\Factory as FakerFactory;
use Faker\Generator;

if (! function_exists('fake')) {
    /**
     * Get a shared Faker instance.
     *
     * This is intentionally defined as a global helper because multiple seeders
     * call fake() directly.
     */
    function fake(?string $locale = null): Generator
    {
        static $fakers = [];

        $locale = $locale ?: config('app.faker_locale', 'en_US');

        return $fakers[$locale] ??= FakerFactory::create($locale);
    }
}


