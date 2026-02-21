<?php

namespace Database\Seeders;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantPrompt;
use App\Models\User;
use Illuminate\Database\Seeder;

class AISeeder extends Seeder
{
    public function run(): void
    {
        $counts = SeedCounts::all();

        $promptKeys = [
            'general_overview' => [
                'ar' => 'Provide a concise platform overview in Arabic.',
                'en' => 'You are the Rakez assistant. Provide a concise platform overview.',
            ],
            'sales_help' => [
                'ar' => 'Explain how to create a new sales reservation in Arabic.',
                'en' => 'Explain how to create a new sales reservation.',
            ],
            'marketing_help' => [
                'ar' => 'Explain how to create a marketing plan in Arabic.',
                'en' => 'Explain how to create a marketing plan.',
            ],
        ];

        $adminId = User::where('type', 'admin')->value('id');
        foreach ($promptKeys as $key => $contents) {
            foreach ($contents as $language => $content) {
                AssistantPrompt::updateOrCreate(
                    ['key' => $key, 'language' => $language],
                    [
                        'content_md' => $content,
                        'is_active' => true,
                        'updated_by' => $adminId,
                    ]
                );
            }
        }

        $allUsers = User::pluck('id')->all();
        for ($i = 0; $i < $counts['assistant_conversations']; $i++) {
            $conversation = AssistantConversation::create([
                'user_id' => $allUsers[array_rand($allUsers)],
                'context' => ['source' => 'seed'],
            ]);

            $messageCount = fake()->numberBetween(
                $counts['assistant_messages_min'],
                $counts['assistant_messages_max']
            );

            for ($m = 0; $m < $messageCount; $m++) {
                AssistantMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => $m % 2 === 0 ? 'user' : 'assistant',
                    'content' => $m % 2 === 0 ? fake()->sentence() : fake()->paragraph(),
                    'capability_used' => $m % 2 === 0 ? null : 'use-ai-assistant',
                    'tokens' => fake()->numberBetween(50, 300),
                    'latency_ms' => fake()->numberBetween(100, 800),
                ]);
            }
        }
    }
}
