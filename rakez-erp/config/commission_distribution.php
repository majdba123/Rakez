<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Commission Distribution Types (أنواع توزيع العمولات)
    |--------------------------------------------------------------------------
    | القيم المعتمدة في الـ API والفرونت. يُستحسن أن يطابق السيدر والجداول هذه القيم.
    */

    'types' => [
        'lead_generation',
        'persuasion',
        'closing',
        'team_leader',
        'assistant_pm',
        'project_manager',
        'owner',
        'sales_manager',
        'projects_department',
        'management',
        'ceo',
        'external_marketer',
        'other',
    ],

    'type_labels' => [
        'lead_generation' => 'عمولة الجلب',
        'persuasion' => 'عمولة الإقناع',
        'closing' => 'عمولة الإقفال',
        'team_leader' => 'قائد الفريق',
        'assistant_pm' => 'مساعد مدير مشروع',
        'project_manager' => 'مدير مشروع',
        'owner' => 'المالك',
        'sales_manager' => 'مدير المبيعات',
        'projects_department' => 'قسم المشاريع',
        'management' => 'الإدارة',
        'ceo' => 'CEO',
        'external_marketer' => 'مسوق خارجي',
        'other' => 'أخرى',
    ],

];
