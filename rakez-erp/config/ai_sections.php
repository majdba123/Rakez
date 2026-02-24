<?php

return [
    'general' => [
        'label' => 'General',
        'required_capabilities' => [],
        'allowed_context_params' => [],
        'suggestions' => [
            'How do I navigate the ERP?',
            'What can I do in this system?',
            'Explain common statuses.',
        ],
    ],
    'contracts' => [
        'label' => 'Contracts',
        'required_capabilities' => ['contracts.view'],
        'allowed_context_params' => ['contract_id'],
        'context_schema' => [
            'contract_id' => 'int|min:1',
        ],
        'context_policy' => [
            'contract_id' => 'view-contract',
        ],
        'suggestions' => [
            'How do I create a contract?',
            'Why is a contract pending?',
            'Explain contract statuses.',
        ],
    ],
    'units' => [
        'label' => 'Units',
        'required_capabilities' => ['units.view'],
        'allowed_context_params' => ['contract_id', 'unit_id'],
        'context_schema' => [
            'contract_id' => 'int|min:1',
            'unit_id' => 'int|min:1',
        ],
        'context_policy' => [
            'contract_id' => 'view-contract',
            'unit_id' => 'view-unit',
        ],
        'suggestions' => [
            'How do I edit a unit?',
            'What are the unit statuses?',
            'How do I upload units by CSV?',
        ],
    ],
    'units_csv' => [
        'label' => 'Units CSV Upload',
        'required_capabilities' => ['units.csv_upload'],
        'allowed_context_params' => ['contract_id'],
        'context_schema' => [
            'contract_id' => 'int|min:1',
        ],
        'context_policy' => [
            'contract_id' => 'view-contract',
        ],
        'parent' => 'units',
        'suggestions' => [
            'What is the correct CSV format?',
            'What are common CSV upload errors?',
            'Which validations are enforced?',
        ],
    ],
    'second_party' => [
        'label' => 'Second Party Data',
        'required_capabilities' => ['second_party.view'],
        'allowed_context_params' => ['contract_id'],
        'context_schema' => [
            'contract_id' => 'int|min:1',
        ],
        'context_policy' => [
            'contract_id' => 'view-contract',
        ],
        'suggestions' => [
            'What documents are required?',
            'How do I update second party data?',
            'How is this linked to a contract?',
        ],
    ],
    'departments_boards' => [
        'label' => 'Boards Department',
        'required_capabilities' => ['departments.boards.view'],
        'allowed_context_params' => ['contract_id'],
        'context_schema' => [
            'contract_id' => 'int|min:1',
        ],
        'context_policy' => [
            'contract_id' => 'view-contract',
        ],
        'suggestions' => [
            'What does the Boards department do?',
            'How do I update boards status?',
            'What data is required from me?',
        ],
    ],
    'departments_photography' => [
        'label' => 'Photography Department',
        'required_capabilities' => ['departments.photography.view'],
        'allowed_context_params' => ['contract_id'],
        'context_schema' => [
            'contract_id' => 'int|min:1',
        ],
        'context_policy' => [
            'contract_id' => 'view-contract',
        ],
        'suggestions' => [
            'What does the Photography department do?',
            'How do I update photography status?',
            'What assets are required?',
        ],
    ],
    'departments_montage' => [
        'label' => 'Montage Department',
        'required_capabilities' => ['departments.montage.view'],
        'allowed_context_params' => ['contract_id'],
        'context_schema' => [
            'contract_id' => 'int|min:1',
        ],
        'context_policy' => [
            'contract_id' => 'view-contract',
        ],
        'suggestions' => [
            'What does the Montage department do?',
            'How do I update montage status?',
            'What assets are required?',
        ],
    ],
    'notifications' => [
        'label' => 'Notifications',
        'required_capabilities' => ['notifications.view'],
        'allowed_context_params' => [],
        'suggestions' => [
            'How do I mark notifications as read?',
            'What are public vs private notifications?',
            'Why did I get this notification?',
        ],
    ],
    'dashboard' => [
        'label' => 'Dashboard',
        'required_capabilities' => ['dashboard.analytics.view'],
        'allowed_context_params' => [],
        'suggestions' => [
            'Explain the dashboard KPIs.',
            'What does total units mean?',
            'How should I interpret trends?',
        ],
    ],
    'marketing_dashboard' => [
        'label' => 'Marketing Dashboard',
        'required_capabilities' => ['marketing.dashboard.view'],
        'allowed_context_params' => [],
        'suggestions' => [
            'What is the current lead count?',
            'Show me the daily task achievement rate.',
            'What is the average deposit cost?',
        ],
    ],
    'marketing_projects' => [
        'label' => 'Marketing Projects',
        'required_capabilities' => ['marketing.projects.view'],
        'allowed_context_params' => ['contract_id'],
        'context_schema' => [
            'contract_id' => 'int|min:1',
        ],
        'context_policy' => [
            'contract_id' => 'view-contract',
        ],
        'suggestions' => [
            'List all marketing projects.',
            'Show me the marketing plan for project X.',
            'What is the budget for this project?',
        ],
    ],
    'marketing_tasks' => [
        'label' => 'Marketing Tasks',
        'required_capabilities' => ['marketing.tasks.view'],
        'allowed_context_params' => ['contract_id'],
        'context_schema' => [
            'contract_id' => 'int|min:1',
        ],
        'suggestions' => [
            'What are my tasks for today?',
            'Show tasks for project Y.',
            'What is the status of task Z?',
        ],
    ],
    'sales' => [
        'label' => 'Sales',
        'required_capabilities' => ['sales.dashboard.view'],
        'allowed_context_params' => [],
        'suggestions' => [
            'كيف أحسن نسبة الإغلاق؟',
            'وش أفضل طريقة أتابع العملاء؟',
            'كيف أحسب العمولة؟',
        ],
    ],
    'hr' => [
        'label' => 'Human Resources',
        'required_capabilities' => ['hr.dashboard.view'],
        'allowed_context_params' => [],
        'suggestions' => [
            'وش المهارات المطلوبة لمستشار مبيعات؟',
            'كيف أقيّم أداء الموظف؟',
            'كم التكلفة الشهرية للموظف؟',
        ],
    ],
    'credit' => [
        'label' => 'Credit & Financing',
        'required_capabilities' => ['credit.dashboard.view'],
        'allowed_context_params' => [],
        'suggestions' => [
            'كيف أحسب القسط الشهري للتمويل؟',
            'وش مراحل التمويل البنكي؟',
            'كم الحد الأدنى للراتب للتمويل؟',
        ],
    ],
    'accounting' => [
        'label' => 'Accounting',
        'required_capabilities' => ['accounting.dashboard.view'],
        'allowed_context_params' => [],
        'suggestions' => [
            'كيف أوزع العمولات على الفريق؟',
            'كيف أتابع الإيداعات؟',
            'وش حالة الرواتب هالشهر؟',
        ],
    ],
    'campaign_advisor' => [
        'label' => 'Campaign Advisor',
        'required_capabilities' => ['marketing.dashboard.view'],
        'allowed_context_params' => [],
        'suggestions' => [
            'عندي ميزانية 50 ألف، وش أفضل توزيع؟',
            'كم ليد أتوقع من 30 ألف بسناب شات؟',
            'قارن لي بين القنوات الإعلانية.',
        ],
    ],
    'hiring_advisor' => [
        'label' => 'Hiring Advisor',
        'required_capabilities' => ['hr.employees.manage'],
        'allowed_context_params' => [],
        'suggestions' => [
            'وش الأسئلة المهمة بمقابلة مستشار مبيعات؟',
            'كيف أبني فريق تسويق لـ 3 مشاريع؟',
            'كم تكلفة موظف التسويق الشهرية؟',
        ],
    ],
    'smart_distribution' => [
        'label' => 'Smart Budget Distribution',
        'required_capabilities' => ['marketing.dashboard.view'],
        'allowed_context_params' => ['budget', 'goal', 'project_type'],
        'context_schema' => [
            'budget' => 'numeric|min:0',
            'goal' => 'string|in:awareness,leads,bookings',
            'project_type' => 'string|in:on_map,ready,exclusive,luxury',
        ],
        'suggestions' => [
            'وزع لي 100 ألف ميزانية تسويق على المنصات',
            'وش أفضل توزيع لميزانية حملة ليدات؟',
            'قارن أداء المنصات وأعطني توصية ميزانية',
        ],
    ],
    'employee_recommendation' => [
        'label' => 'Employee Recommendation',
        'required_capabilities' => ['sales.team.manage'],
        'allowed_context_params' => ['project_id', 'project_type'],
        'context_schema' => [
            'project_id' => 'int|min:1',
            'project_type' => 'string|in:on_map,ready,exclusive,luxury',
        ],
        'suggestions' => [
            'رشّح لي أفضل 5 موظفين لمشروع جديد',
            'مين أفضل الموظفين لمشاريع الخارطة؟',
            'قيّم أداء فريق المبيعات',
        ],
    ],
    'campaign_funnel' => [
        'label' => 'Campaign Funnel Analytics',
        'required_capabilities' => ['marketing.dashboard.view'],
        'allowed_context_params' => ['platform', 'project_id'],
        'context_schema' => [
            'platform' => 'string|in:meta,snap,tiktok',
            'project_id' => 'int|min:1',
        ],
        'suggestions' => [
            'حلل لي قمع التسويق الكامل',
            'وين عنق الزجاجة بالحملات؟',
            'قارن أداء المنصات بالتفصيل',
        ],
    ],
    'roas_optimizer' => [
        'label' => 'ROAS Optimizer',
        'required_capabilities' => ['marketing.dashboard.view', 'accounting.dashboard.view'],
        'allowed_context_params' => ['platform'],
        'context_schema' => [
            'platform' => 'string|in:meta,snap,tiktok',
        ],
        'suggestions' => [
            'وش عائد الإنفاق الإعلاني الفعلي؟',
            'كم تكلفة الحصول على صفقة مغلقة؟',
            'حلل كفاءة الإنفاق التسويقي',
        ],
    ],
];
