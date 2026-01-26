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
];
