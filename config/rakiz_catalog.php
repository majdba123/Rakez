<?php

/**
 * Rakiz System Catalog — SINGLE SOURCE OF TRUTH
 *
 * All AI components (SystemPromptBuilder, RakizAiOrchestrator, CatalogService)
 * MUST use this catalog for sections, permissions, and role mappings.
 *
 * This file is derived from ai_capabilities.php and ai_sections.php but serves
 * as the canonical reference for the AI layer.
 */

$sections = config('ai_sections', []);
$capabilities = config('ai_capabilities', []);

$sectionMap = [];
foreach ($sections as $key => $sec) {
    $sectionMap[$key] = $sec['label'] ?? $key;
}

$permissionDefinitions = $capabilities['definitions'] ?? [];

$roleMap = $capabilities['bootstrap_role_map'] ?? [];

$roleSections = [];
foreach ($roleMap as $role => $permissions) {
    $accessible = [];
    foreach ($sections as $sectionKey => $sectionConfig) {
        $required = $sectionConfig['required_capabilities'] ?? [];
        if (empty($required) || empty(array_diff($required, $permissions))) {
            $accessible[] = $sectionKey;
        }
    }
    $roleSections[$role] = $accessible;
}

$roles = [];
foreach ($roleMap as $role => $permissions) {
    $roles[$role] = [
        'permissions' => $permissions,
        'sections' => $roleSections[$role] ?? [],
    ];
}

return [
    'sections' => $sectionMap,
    'permissions' => $permissionDefinitions,
    'roles' => $roles,

    'market_references' => [
        'cpl' => ['min' => 15, 'max' => 150, 'unit' => 'SAR', 'label' => 'تكلفة الليد'],
        'close_rate' => ['min' => 5, 'max' => 15, 'unit' => '%', 'label' => 'نسبة الإغلاق'],
        'mortgage_max_dti' => 55,
    ],
];
