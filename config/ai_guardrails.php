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
];
