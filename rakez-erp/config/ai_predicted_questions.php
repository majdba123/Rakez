<?php

/**
 * Curated prompts for E2E tool-calling runs (Arabic-first; English variants where useful).
 * Sections align with config('ai_assistant.tools.sections') + common flows.
 */
return [
    [
        'id' => 'sales_kpi_ar',
        'section' => 'sales',
        'message' => 'أعطني ملخصاً لمؤشرات المبيعات والأداء (KPI) المتاحة لي في النظام، مع أرقام إن وُجدت.',
    ],
    [
        'id' => 'sales_pipeline_ar',
        'section' => 'sales',
        'message' => 'ما هي حالة الصفقات والحجوزات الأخيرة التي يمكنك عرضها من النظام؟',
    ],
    [
        'id' => 'marketing_campaigns_ar',
        'section' => 'marketing',
        'message' => 'اقترح تحسينات لحملات الإعلانات الرقمية (ميتا/تيك توك/سناب) بناءً على البيانات المتاحة.',
    ],
    [
        'id' => 'marketing_roi_ar',
        'section' => 'marketing',
        'message' => 'اشرح مؤشرات الأداء التسويقي مثل ROI وROMI وكيف أتابعها في لوحة التحكم.',
    ],
    [
        'id' => 'finance_installment_ar',
        'section' => 'finance',
        'message' => 'احسب قسطاً شهرياً تقريبياً لقرض قيمته 800000 ريال سعودي لمدة 20 سنة بفائدة سنوية 5%.',
    ],
    [
        'id' => 'finance_budget_ar',
        'section' => 'finance',
        'message' => 'ما هي نقاط الحذر المالية عند تقديم عروض تمويل للعملاء؟',
    ],
    [
        'id' => 'hr_hiring_ar',
        'section' => 'hr',
        'message' => 'ما هي أفضل الممارسات لتوظيف مندوبي مبيعات للقطاع العقاري؟',
    ],
    [
        'id' => 'hr_performance_ar',
        'section' => 'hr',
        'message' => 'اقترح مؤشرات لقياس أداء فريق المبيعات بشكل عادل.',
    ],
    [
        'id' => 'rag_policy_ar',
        'section' => null,
        'message' => 'هل توجد سياسات أو مستندات داخلية حول الإلغاء أو التمويل يمكن استرجاعها؟',
    ],
    [
        'id' => 'marketing_campaigns_en',
        'section' => 'marketing',
        'message' => 'Summarize campaign performance metrics I should watch for Meta and TikTok ads in one paragraph.',
    ],
    [
        'id' => 'sales_leads_ar',
        'section' => 'sales',
        'message' => 'هل يمكنك جلب ملخص لعميل محتمل أو عرض لائحة العملاء إن كانت الأدوات متاحة؟',
    ],
    [
        'id' => 'access_explain_ar',
        'section' => null,
        'message' => 'ما الصلاحيات التي أحتاجها لعرض عقود معينة في النظام؟',
    ],
    [
        'id' => 'tool_keywords_en',
        'section' => 'marketing',
        'message' => 'Analyze our campaign funnel and suggest budget optimization using KPI and ROI data.',
    ],
];
