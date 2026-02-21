<?php

return [
    /*
    |--------------------------------------------------------------------------
    | First party (company) details for contracts
    |--------------------------------------------------------------------------
    | Used when creating/updating contract info. Override via .env if needed.
    */
    'first_party_name' => env('CONTRACT_FIRST_PARTY_NAME', 'شركة راكز العقارية'),
    'first_party_cr_number' => env('CONTRACT_FIRST_PARTY_CR_NUMBER', '1010650301'),
    'first_party_signatory' => env('CONTRACT_FIRST_PARTY_SIGNATORY', 'عبد العزيز خالد عبد العزيز الجلعود'),
    'first_party_phone' => env('CONTRACT_FIRST_PARTY_PHONE', '0935027218'),
    'first_party_email' => env('CONTRACT_FIRST_PARTY_EMAIL', 'info@rakez.sa'),
];
