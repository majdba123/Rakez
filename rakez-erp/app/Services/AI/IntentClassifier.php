<?php

namespace App\Services\AI;

class IntentClassifier
{
    /**
     * Patterns that indicate the user is asking about available sections / capabilities.
     */
    private const CATALOG_PATTERNS = [
        // Arabic patterns
        'أقسامي',
        'صلاحياتي',
        'وش أقدر أسوي',
        'ايش أقدر أسوي',
        'ايش الأقسام',
        'وش الأقسام',
        'الأقسام المتاحة',
        'أقسام النظام',
        'صلاحيات النظام',
        'وش عندي من صلاحيات',
        'ايش عندي صلاحيات',
        'قائمة الأقسام',
        'كم قسم عندي',
        'وش الخدمات',
        'ايش الخدمات',
        // English patterns
        'my sections',
        'my permissions',
        'what can i do',
        'what sections',
        'available sections',
        'list sections',
        'show sections',
        'what are my capabilities',
        'what services',
    ];

    public function __construct(
        private readonly CatalogService $catalogService,
    ) {}

    /**
     * Classify the user's message into an intent.
     *
     * @return array{intent: string}
     */
    public function classify(string $message): array
    {
        $normalized = mb_strtolower(trim($message));

        foreach (self::CATALOG_PATTERNS as $pattern) {
            if (mb_strpos($normalized, mb_strtolower($pattern)) !== false) {
                return ['intent' => 'catalog_query'];
            }
        }

        return ['intent' => 'general'];
    }
}
