<?php

return [
    'panel' => [
        'brand_name' => 'حوكمة راكز',
    ],

    'navigation' => [
        'groups' => [
            'overview' => 'نظرة عامة',
            'access_governance' => 'حوكمة الوصول',
            'governance_observability' => 'مراقبة الحوكمة',
            'credit_oversight' => 'إشراف الائتمان',
            'accounting_finance' => 'المحاسبة والمالية',
            'contracts_projects' => 'العقود والمشاريع',
            'sales_oversight' => 'إشراف المبيعات',
            'hr_oversight' => 'إشراف الموارد البشرية',
            'marketing_oversight' => 'إشراف التسويق',
            'inventory_oversight' => 'إشراف المخزون',
            'ai_knowledge' => 'الذكاء الاصطناعي والمعرفة',
            'requests_workflow' => 'الطلبات وسير العمل',
        ],
    ],

    'stepper' => [
        'state' => [
            'completed' => 'مكتمل',
            'current' => 'الحالي',
            'failed' => 'يتطلب معالجة',
            'pending' => 'قيد الانتظار',
            'skipped' => 'متجاوز',
        ],
    ],

    'role_aliases' => [
        'admin' => 'مسؤول',
        'legacy_admin' => 'مسؤول قديم',
    ],

    'status' => [
        'sales_target' => [
            'new' => 'جديد',
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتمل',
        ],
        'marketing_task' => [
            'pending' => 'قيد الانتظار',
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتمل',
        ],
    ],

    'resources' => [
        'users' => [
            'navigation_label' => 'المستخدمون',
            'fields' => [
                'name' => 'الاسم',
                'email' => 'البريد الإلكتروني',
                'phone' => 'رقم الهاتف',
                'password' => 'كلمة المرور',
                'type' => 'نوع المستخدم',
                'manager' => 'مدير',
                'team' => 'الفريق',
                'active' => 'نشط',
                'additional_roles' => 'أدوار إضافية',
                'admin_roles' => 'أدوار الحوكمة',
                'direct_permissions' => 'صلاحيات مباشرة',
                'effective_access' => 'ملخص الوصول الفعلي',
            ],
            'columns' => [
                'name' => 'الاسم',
                'email' => 'البريد الإلكتروني',
                'type' => 'النوع',
                'manager' => 'مدير',
                'team' => 'الفريق',
                'governance_roles' => 'أدوار الحوكمة',
                'direct_permissions' => 'صلاحيات مباشرة',
                'active' => 'نشط',
                'deleted' => 'محذوف',
            ],
            'helper' => [
                'legacy_admin' => 'نوع المستخدم admin القديم محفوظ للتوافق، ولا يمكن إسناده جديدا من هذه الشاشة.',
                'additional_roles' => 'أدوار تشغيلية إضافية بجانب الدور الأساسي المشتق من نوع المستخدم.',
                'admin_roles' => 'يمكن إسناد دور المسؤول الأعلى فقط من قبل مسؤول أعلى.',
                'direct_permissions' => 'امنح أو اسحب الصلاحيات الفردية مباشرة لهذا المستخدم.',
                'effective_access_after_create' => 'يتاح بعد إنشاء المستخدم.',
            ],
        ],

        'roles' => [
            'navigation_label' => 'الأدوار',
            'fields' => [
                'name' => 'الدور',
                'category' => 'التصنيف',
                'permissions' => 'الصلاحيات',
            ],
            'columns' => [
                'name' => 'الدور',
                'users' => 'المستخدمون',
                'permissions' => 'الصلاحيات',
                'category' => 'التصنيف',
            ],
            'category' => [
                'legacy_operational' => 'دور تشغيلي قديم',
                'governance_overlay' => 'دور حوكمة إضافي',
                'future_section' => 'دور قسم حوكمة مستقبلي',
                'system' => 'دور نظام',
                'legacy' => 'قديم',
                'governance' => 'حوكمة',
                'future_section_short' => 'قسم مستقبلي',
                'system_short' => 'نظام',
            ],
            'helper' => [
                'permissions' => 'الصلاحيات مأخوذة من قاموس الحوكمة ومن جدول الصلاحيات في قاعدة البيانات.',
            ],
        ],

        'permissions' => [
            'navigation_label' => 'الصلاحيات',
            'columns' => [
                'name' => 'الصلاحية',
                'description' => 'الوصف',
                'roles' => 'الأدوار',
                'direct_users' => 'مستخدمون مباشَرون',
                'guard' => 'الحارس',
            ],
        ],

        'direct_permissions' => [
            'navigation_label' => 'الصلاحيات المباشرة',
            'fields' => [
                'user' => 'المستخدم',
                'email' => 'البريد الإلكتروني',
                'current_roles' => 'الأدوار الحالية',
                'direct_permissions' => 'الصلاحيات المباشرة',
                'effective_access' => 'ملخص الوصول الفعلي',
            ],
            'columns' => [
                'name' => 'المستخدم',
                'email' => 'البريد الإلكتروني',
                'direct_permissions' => 'الصلاحيات المباشرة',
                'roles' => 'الأدوار',
                'panel_access' => 'وصول اللوحة',
                'deleted' => 'محذوف',
            ],
        ],

        'effective_access' => [
            'summary' => [
                'legacy_roles' => 'الأدوار التشغيلية',
                'governance_roles' => 'أدوار الحوكمة',
                'direct_permissions' => 'الصلاحيات المباشرة',
                'inherited_permissions' => 'الصلاحيات الموروثة',
                'temporary_permissions' => 'الصلاحيات المؤقتة',
                'dynamic_permissions' => 'الصلاحيات الديناميكية',
                'panel_eligible' => 'أهلية الوصول للوحة',
                'yes' => 'نعم',
                'no' => 'لا',
                'none' => 'لا يوجد',
            ],
        ],

        'credit_notifications' => [
            'navigation_label' => 'إشعارات الائتمان',
            'columns' => [
                'recipient' => 'المستلم',
                'event' => 'الحدث',
            ],
            'status' => [
                'pending' => 'قيد الانتظار',
                'read' => 'مقروء',
            ],
            'actions' => [
                'mark_read' => 'تعيين كمقروء',
                'mark_all_read' => 'تعيين الكل كمقروء',
            ],
            'sections' => [
                'review' => 'مراجعة الإشعار',
                'context' => 'السياق',
            ],
            'notifications' => [
                'marked_read' => 'تم تعيين إشعار الائتمان كمقروء.',
                'all_marked_read' => 'تم تعيين جميع إشعارات الائتمان كمقروءة.',
            ],
        ],

        'workflow_tasks' => [
            'navigation_label' => 'مهام سير العمل',
            'columns' => [
                'task' => 'المهمة',
                'team' => 'الفريق',
                'assignee' => 'المكلف',
                'created_by' => 'أنشئت بواسطة',
            ],
            'fields' => [
                'reason' => 'السبب',
                'due_at' => 'تاريخ الاستحقاق',
            ],
            'status' => [
                'in_progress' => 'قيد التنفيذ',
                'completed' => 'مكتملة',
                'could_not_complete' => 'تعذر الإكمال',
            ],
            'actions' => [
                'create' => 'إنشاء مهمة',
                'mark_in_progress' => 'إعادة إلى قيد التنفيذ',
                'mark_completed' => 'تعيين كمكتملة',
                'could_not_complete' => 'تعذر الإكمال',
            ],
            'helper' => [
                'section' => 'اختياري. افتراضيا يتم استخدام نوع مستخدم المكلف.',
            ],
            'notifications' => [
                'created' => 'تم إنشاء المهمة بنجاح.',
                'moved_in_progress' => 'أعيدت المهمة إلى قيد التنفيذ.',
                'marked_completed' => 'تم تعيين المهمة كمكتملة.',
                'marked_not_completable' => 'تم تعيين المهمة كغير قابلة للإكمال.',
            ],
        ],

        'title_transfers' => [
            'navigation_label' => 'نقل الملكية',
            'columns' => [
                'booking' => 'الحجز',
                'project' => 'المشروع',
                'unit' => 'الوحدة',
                'processed_by' => 'تمت المعالجة بواسطة',
                'created' => 'تاريخ الإنشاء',
            ],
            'entries' => [
                'booking_id' => 'رقم الحجز',
                'client' => 'العميل',
                'credit_status' => 'حالة الائتمان',
            ],
            'status' => [
                'preparation' => 'تهيئة',
                'scheduled' => 'مجدول',
                'completed' => 'مكتمل',
            ],
            'actions' => [
                'schedule' => 'جدولة',
                'clear_schedule' => 'إلغاء الجدولة',
                'complete' => 'إكمال',
            ],
            'sections' => [
                'stepper' => 'تقدم النقل',
                'review' => 'مراجعة النقل',
                'reservation' => 'الحجز',
            ],
            'stepper' => [
                'title' => 'دورة نقل الملكية',
                'steps' => [
                    'preparation' => 'تهيئة',
                    'scheduled' => 'مجدول',
                    'completed' => 'مكتمل',
                ],
            ],
            'notifications' => [
                'scheduled' => 'تم جدولة نقل الملكية.',
                'cleared' => 'تم إلغاء جدولة نقل الملكية.',
                'completed' => 'تم إكمال نقل الملكية.',
            ],
        ],

        'claim_files' => [
            'actions' => [
                'generate_bulk' => 'إنشاء ملفات مطالبات مجمعة',
                'generate_combined' => 'إنشاء ملف مطالبة موحّد',
            ],
            'fields' => [
                'sold_bookings' => 'الحجوزات المباعة',
                'claim_type_commission' => 'عمولة',
            ],
            'notifications' => [
                'bulk_generated' => 'تم إنشاء ملفات المطالبات المجمعة.',
                'combined_generated' => 'تم إنشاء ملف المطالبة الموحّد.',
            ],
        ],

        'sales_targets' => [
            'actions' => [
                'set_status' => 'تحديد الحالة',
            ],
            'fields' => [
                'status' => 'الحالة',
            ],
            'notifications' => [
                'status_updated' => 'تم تحديث حالة هدف المبيعات.',
            ],
        ],

        'marketing_tasks' => [
            'actions' => [
                'delete' => 'حذف',
                'mark_completed' => 'تعيين كمكتمل',
            ],
            'notifications' => [
                'deleted' => 'تم حذف مهمة التسويق.',
                'completed' => 'تم تعيين مهمة التسويق كمكتملة.',
            ],
        ],

        'employee_contracts' => [
            'navigation_label' => 'عقود الموظفين',
            'columns' => [
                'employee' => 'الموظف',
                'pdf' => 'ملف PDF',
                'remaining_days' => 'الأيام المتبقية',
                'lifecycle' => 'دورة الحياة',
            ],
            'status' => [
                'draft' => 'مسودة',
                'active' => 'ساري',
                'expired' => 'منتهي',
                'terminated' => 'منهى',
            ],
            'actions' => [
                'create' => 'إنشاء عقد',
                'edit' => 'تعديل',
                'generate_pdf' => 'إنشاء PDF',
                'activate' => 'تفعيل',
                'terminate' => 'إنهاء',
                'lifecycle' => 'دورة الحياة',
            ],
            'modals' => [
                'lifecycle_heading' => 'دورة حياة العقد',
                'close' => 'إغلاق',
            ],
            'stepper' => [
                'steps' => [
                    'draft' => 'مسودة',
                    'active' => 'ساري',
                    'expired' => 'منتهي',
                    'terminated' => 'منهى',
                ],
            ],
            'notifications' => [
                'created' => 'تم إنشاء عقد الموظف.',
                'updated' => 'تم تحديث عقد الموظف.',
                'pdf_generated' => 'تم إنشاء ملف PDF لعقد الموظف.',
                'activated' => 'تم تفعيل عقد الموظف.',
                'terminated' => 'تم إنهاء عقد الموظف.',
            ],
        ],

        'credit_bookings' => [
            'navigation_label' => 'حجوزات الائتمان',
            'columns' => [
                'project' => 'المشروع',
                'unit' => 'الوحدة',
                'client' => 'العميل',
                'credit_status' => 'حالة الائتمان',
                'purchase' => 'آلية الشراء',
                'deposit_confirmed' => 'تأكيد العربون',
                'financing' => 'التمويل',
                'title_transfer' => 'نقل الملكية',
                'claim_file' => 'ملف المطالبة',
            ],
            'status' => [
                'confirmed' => 'مؤكد',
                'under_negotiation' => 'قيد التفاوض',
                'cancelled' => 'ملغي',
            ],
            'credit_status' => [
                'pending' => 'قيد الانتظار',
                'in_progress' => 'قيد التنفيذ',
                'title_transfer' => 'نقل الملكية',
                'sold' => 'مباعة',
                'rejected' => 'مرفوض',
            ],
            'financing_status' => [
                'completed' => 'مكتمل',
            ],
            'purchase' => [
                'cash' => 'نقدي',
                'supported_bank' => 'بنك معتمد',
                'unsupported_bank' => 'بنك غير معتمد',
            ],
            'employment' => [
                'government' => 'حكومي',
                'private' => 'خاص',
            ],
            'filters' => [
                'financing_status' => 'حالة التمويل',
                'has_title_transfer' => 'لديه نقل ملكية',
                'has_claim_file' => 'لديه ملف مطالبة',
            ],
            'actions' => [
                'edit_client' => 'تعديل العميل',
                'log_contact' => 'تسجيل تواصل',
                'cancel' => 'إلغاء',
                'advance_financing' => 'تقدم التمويل',
                'reject_financing' => 'رفض التمويل',
                'generate_claim_file' => 'إنشاء ملف مطالبة',
                'generate_claim_pdf' => 'إنشاء PDF المطالبة',
            ],
            'sections' => [
                'process_progress' => 'تقدم العملية',
                'reservation' => 'الحجز',
                'client_financial' => 'العميل والبيانات المالية',
                'financing' => 'التمويل',
                'transfer_claim' => 'مراجعة النقل والمطالبة',
            ],
            'entries' => [
                'booking_id' => 'رقم الحجز',
                'confirmed_at' => 'تاريخ التأكيد',
                'mobile' => 'الجوال',
                'nationality' => 'الجنسية',
                'iban' => 'الآيبان',
                'down_payment' => 'الدفعة المقدمة',
                'installments' => 'عدد الأقساط',
                'remaining_payment_plan' => 'خطة السداد المتبقية',
                'needs_accounting_confirmation' => 'يحتاج تأكيد المحاسبة',
                'overall_status' => 'الحالة العامة',
                'assigned_to' => 'مسند إلى',
                'current_stage' => 'المرحلة الحالية',
                'remaining_days' => 'الأيام المتبقية',
                'progress_summary' => 'ملخص التقدم',
                'no_financing_tracker' => 'لا يوجد متتبع تمويل',
                'title_transfer_status' => 'حالة نقل الملكية',
                'scheduled_date' => 'التاريخ المجدول',
                'completed_date' => 'تاريخ الإكمال',
                'not_generated' => 'غير منشأ',
                'claim_pdf' => 'ملف مطالبة PDF',
                'claim_amount' => 'مبلغ المطالبة',
            ],
            'notifications' => [
                'client_updated' => 'تم تحديث بيانات عميل الحجز.',
                'contact_logged' => 'تم تسجيل تواصل عميل الائتمان.',
                'cancelled' => 'تم إلغاء الحجز.',
                'financing_advanced' => 'تم تقديم سير عمل التمويل.',
                'financing_rejected' => 'تم رفض طلب التمويل.',
                'claim_file_generated' => 'تم إنشاء ملف المطالبة.',
                'claim_pdf_ready' => 'ملف PDF للمطالبة جاهز.',
            ],
            'stepper' => [
                'reservation_title' => 'دورة الحجز',
                'financing_title' => 'مراحل التمويل',
                'transfer_title' => 'نقل الملكية',
                'financing_not_required' => 'التمويل غير مطلوب لهذا الحجز',
                'financing_not_started' => 'لم يتم بدء متتبع التمويل',
                'transfer_not_started' => 'لم يبدأ نقل الملكية',
                'steps' => [
                    'confirmed' => 'تأكيد الحجز',
                    'financing' => 'التمويل',
                    'title_transfer' => 'نقل الملكية',
                    'sold' => 'إتمام البيع',
                ],
                'stages' => [
                    'stage' => 'المرحلة :number',
                ],
                'transfer_steps' => [
                    'preparation' => 'تهيئة',
                    'scheduled' => 'مجدول',
                    'completed' => 'مكتمل',
                ],
            ],
            'stage' => [
                'label' => 'المرحلة :number',
                'value_with_deadline' => ':status (الموعد النهائي :deadline)',
            ],
        ],
    ],
];
