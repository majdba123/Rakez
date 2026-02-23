<?php

namespace App\Services\AI;

class DenyTemplateBuilder
{
    /**
     * Build a deterministic deny message in Saudi Arabic.
     *
     * @param string $permission  The missing permission key
     * @param string $action      What the user was trying to do
     * @param string[] $alternatives  Suggested alternative actions the user CAN do
     */
    public static function build(string $permission, string $action, array $alternatives = []): string
    {
        $msg = "ما عندك صلاحية [{$permission}] عشان تسوي [{$action}].";

        if (! empty($alternatives)) {
            $msg .= ' تقدر بدلها: ' . implode('، ', $alternatives) . '.';
        }

        return $msg;
    }

    /**
     * Build a deny response array suitable for the AI output schema.
     */
    public static function response(string $permission, string $action, array $alternatives = []): array
    {
        return [
            'answer_markdown' => self::build($permission, $action, $alternatives),
            'confidence' => 'high',
            'sources' => [],
            'links' => [],
            'suggested_actions' => [],
            'follow_up_questions' => [],
            'access_notes' => [
                'had_denied_request' => true,
                'reason' => "صلاحية مفقودة: {$permission}",
            ],
        ];
    }
}
