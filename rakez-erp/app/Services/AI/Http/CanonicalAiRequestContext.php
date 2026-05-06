<?php

namespace App\Services\AI\Http;

use Illuminate\Http\Request;

class CanonicalAiRequestContext
{
    public function message(Request $request): string
    {
        return (string) $request->input('message');
    }

    public function conversationId(Request $request): ?string
    {
        $conversationId = $request->input('conversation_id');

        return $conversationId !== null ? (string) $conversationId : null;
    }

    public function section(Request $request): string
    {
        return (string) $request->input('section', 'general');
    }

    public function locale(Request $request): string
    {
        $locale = $request->input('locale');
        if ($locale === 'ar' || $locale === 'en') {
            return $locale;
        }

        return $this->inferLocale($this->message($request));
    }

    public function inputMode(Request $request): string
    {
        return $request->input('input_mode') === 'voice' ? 'voice' : 'text';
    }

    /**
     * @return array<string, mixed>
     */
    public function erpContext(Request $request): array
    {
        return array_merge(
            (array) $request->input('page_context', []),
            (array) $request->input('context', [])
        );
    }

    private function inferLocale(string $message): string
    {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $message) === 1 ? 'ar' : 'en';
    }
}
