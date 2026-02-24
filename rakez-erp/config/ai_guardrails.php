<?php

/**
 * Saudi market numeric reference ranges for AI guardrails.
 * Used by NumericGuardrails service to validate tool outputs.
 */
return [
    'cpl' => [
        'min' => 15,
        'max' => 150,
        'unit' => 'SAR',
        'label' => 'تكلفة الليد',
        'channels' => [
            'google' => ['min' => 30, 'max' => 80],
            'snapchat' => ['min' => 12, 'max' => 50],
            'instagram' => ['min' => 20, 'max' => 60],
            'tiktok' => ['min' => 10, 'max' => 40],
        ],
    ],

    'close_rate' => [
        'min' => 5,
        'max' => 15,
        'unit' => '%',
        'label' => 'نسبة الإغلاق',
    ],

    'romi' => [
        'min' => 100,
        'max' => 2000,
        'unit' => '%',
        'label' => 'عائد الاستثمار التسويقي (ROMI)',
    ],

    'project_roi' => [
        'min' => 10,
        'max' => 500,
        'unit' => '%',
        'label' => 'عائد الاستثمار الشامل للمشروع',
    ],

    'conversion_rate' => [
        'min' => 3,
        'max' => 25,
        'unit' => '%',
        'label' => 'معدل التحويل',
    ],

    'mortgage' => [
        'max_dti' => 55,
        'label' => 'نسبة الاستقطاع القصوى (ساما)',
    ],

    'regional_benchmarks' => [
        'الرياض' => ['cpl_multiplier' => 1.15, 'conversion_bonus' => 0],
        'جدة' => ['cpl_multiplier' => 1.0, 'conversion_bonus' => 0.01],
        'الدمام' => ['cpl_multiplier' => 0.85, 'conversion_bonus' => 0.02],
        'المنطقة الشرقية' => ['cpl_multiplier' => 0.80, 'conversion_bonus' => 0.02],
    ],

    'seasonal_adjustments' => [
        'ramadan' => ['spend_multiplier' => 0.7, 'label' => 'رمضان — خفض الإنفاق 30%'],
        'summer' => ['spend_multiplier' => 0.85, 'label' => 'صيف — إجازات تقلل التفاعل'],
        'national_day' => ['spend_multiplier' => 1.2, 'label' => 'اليوم الوطني — زيادة العروض والطلب'],
        'year_end' => ['spend_multiplier' => 1.15, 'label' => 'نهاية السنة — تسارع قرارات الشراء'],
    ],

    'project_type_conversion_rates' => [
        'on_map' => ['min' => 4, 'max' => 8, 'label' => 'مشاريع على الخارطة'],
        'ready' => ['min' => 7, 'max' => 15, 'label' => 'مشاريع جاهزة'],
        'exclusive' => ['min' => 8, 'max' => 18, 'label' => 'مشاريع حصرية'],
        'luxury' => ['min' => 3, 'max' => 7, 'label' => 'مشاريع فاخرة'],
    ],

    'platform_meta' => [
        'cpl_range' => ['min' => 20, 'max' => 60],
        'best_for' => 'leads,bookings',
        'label' => 'Meta (Facebook/Instagram)',
    ],
    'platform_snap' => [
        'cpl_range' => ['min' => 12, 'max' => 50],
        'best_for' => 'awareness,leads',
        'label' => 'Snapchat',
    ],
    'platform_tiktok' => [
        'cpl_range' => ['min' => 10, 'max' => 40],
        'best_for' => 'awareness,leads',
        'label' => 'TikTok',
    ],
];
