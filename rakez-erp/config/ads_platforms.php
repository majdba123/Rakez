<?php

return [

    'meta' => [
        'access_token' => env('META_ADS_ACCESS_TOKEN'),
        'pixel_id' => env('META_PIXEL_ID'),
        'ad_account_id' => env('META_AD_ACCOUNT_ID'),
        'api_version' => env('META_API_VERSION', 'v22.0'),
        'base_url' => 'https://graph.facebook.com',
        'test_event_code' => env('META_TEST_EVENT_CODE'),
    ],

    'snap' => [
        'client_id' => env('SNAP_CLIENT_ID'),
        'client_secret' => env('SNAP_CLIENT_SECRET'),
        'refresh_token' => env('SNAP_REFRESH_TOKEN'),
        'pixel_id' => env('SNAP_PIXEL_ID'),
        'ad_account_id' => env('SNAP_AD_ACCOUNT_ID'),
        'ads_base_url' => 'https://adsapi.snapchat.com/v1',
        'capi_base_url' => 'https://tr.snapchat.com/v3',
        'auth_url' => 'https://accounts.snapchat.com/login/oauth2/access_token',
    ],

    'tiktok' => [
        'app_id' => env('TIKTOK_APP_ID'),
        'app_secret' => env('TIKTOK_APP_SECRET'),
        'access_token' => env('TIKTOK_ACCESS_TOKEN'),
        'refresh_token' => env('TIKTOK_REFRESH_TOKEN'),
        'pixel_code' => env('TIKTOK_PIXEL_CODE'),
        'advertiser_id' => env('TIKTOK_ADVERTISER_ID'),
        'base_url' => 'https://business-api.tiktok.com/open_api/v1.3',
        'auth_url' => 'https://open.tiktokapis.com/v2/oauth/token/',
    ],

    'sync' => [
        'campaign_structure_interval' => env('ADS_CAMPAIGN_SYNC_HOURS', 6),
        'insights_lookback_days' => env('ADS_INSIGHTS_LOOKBACK_DAYS', 7),
        'outcome_publish_interval_seconds' => env('ADS_OUTCOME_INTERVAL', 60),
    ],

];
