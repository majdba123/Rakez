<?php

return [

    /** Normalized currency for CRM/outbox rows when upstream sends a different provider currency. */
    'default_normalized_currency' => env('ADS_DEFAULT_NORMALIZED_CURRENCY', 'USD'),

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'access_token' => env('META_ADS_ACCESS_TOKEN'),
        'pixel_id' => env('META_PIXEL_ID'),
        'ad_account_id' => env('META_AD_ACCOUNT_ID'),
        'api_version' => env('META_API_VERSION', 'v22.0'),
        'base_url' => 'https://graph.facebook.com',
        'test_event_code' => env('META_TEST_EVENT_CODE'),
        // Lead Ads retrieval requires token with leads_retrieval, pages_manage_ads, pages_read_engagement, pages_show_list, ads_management
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
        /** Relative to ads_base_url; use {ad_account_id} placeholder. */
        'lead_forms_list_path' => env('SNAP_LEAD_FORMS_LIST_PATH', 'adaccounts/{ad_account_id}/lead_generation_forms'),
        /** Relative to ads_base_url; use {lead_form_id} placeholder. */
        'leads_for_form_path' => env('SNAP_LEADS_FOR_FORM_PATH', 'lead_generation_forms/{lead_form_id}/leads'),
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
        'insights' => [
            // TikTok requires service_type for integrated reports. AUCTION is the default for auction ads.
            'service_type' => env('TIKTOK_INSIGHTS_SERVICE_TYPE', 'AUCTION'),

            // Metrics list is provider-specific; keep it configurable to avoid code changes when TikTok updates names.
            'metrics' => array_values(array_filter(array_map('trim', explode(',', env(
                'TIKTOK_INSIGHTS_METRICS',
                'spend,impressions,clicks,reach,conversion,cost_per_conversion,conversion_rate,video_play_actions,video_watched_6s'
            ))))),

            // Optional: map provider metrics into normalized fields (leads/revenue/conversions).
            // Leave empty to keep revenue/leads at 0 while preserving raw_metrics for downstream modeling.
            'lead_metric_keys' => array_values(array_filter(array_map('trim', explode(',', env('TIKTOK_INSIGHTS_LEAD_METRIC_KEYS', ''))))),
            'revenue_metric_keys' => array_values(array_filter(array_map('trim', explode(',', env('TIKTOK_INSIGHTS_REVENUE_METRIC_KEYS', ''))))),
            'conversion_metric_keys' => array_values(array_filter(array_map('trim', explode(',', env('TIKTOK_INSIGHTS_CONVERSION_METRIC_KEYS', 'conversion'))))),
        ],
    ],

    // All insights/campaign data is fetched 100% from platform APIs (Meta, Snap, TikTok) and stored in ads_* tables. No static or mock data.
    'sync' => [
        'campaign_structure_interval' => env('ADS_CAMPAIGN_SYNC_HOURS', 6),
        'insights_lookback_days' => env('ADS_INSIGHTS_LOOKBACK_DAYS', 30),
        'leads_lookback_days' => env('ADS_LEADS_LOOKBACK_DAYS', 7),
        'leads_interval_hours' => env('ADS_LEADS_SYNC_HOURS', 6),
        'outcome_publish_interval_seconds' => env('ADS_OUTCOME_INTERVAL', 60),
    ],

];
