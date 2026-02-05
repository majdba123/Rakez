<?php

namespace Database\Seeders;

use App\Models\AdminNotification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationsSeeder extends Seeder
{
    public function run(): void
    {
        $counts = SeedCounts::all();
        $adminIds = User::where('type', 'admin')->pluck('id')->all();
        $allUsers = User::pluck('id')->all();

        for ($i = 0; $i < $counts['admin_notifications']; $i++) {
            if (!$adminIds) {
                break;
            }
            AdminNotification::create([
                'user_id' => $adminIds[array_rand($adminIds)],
                'message' => 'Seeded admin notification #' . ($i + 1),
                'status' => $i % 2 === 0 ? 'pending' : 'read',
            ]);
        }

        for ($i = 0; $i < $counts['user_notifications']; $i++) {
            $isPublic = $i % 2 === 0;
            UserNotification::create([
                'user_id' => $isPublic ? null : $allUsers[array_rand($allUsers)],
                'message' => 'Seeded user notification #' . ($i + 1),
                'status' => $i % 3 === 0 ? 'read' : 'pending',
            ]);
        }

        if ($allUsers) {
            $rows = [];
            for ($i = 0; $i < 5; $i++) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'type' => 'App\\Notifications\\GenericNotification',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $allUsers[array_rand($allUsers)],
                    'data' => json_encode(['message' => 'Seeded notification', 'index' => $i + 1]),
                    'read_at' => $i % 2 === 0 ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('notifications')->insert($rows);
        }
    }
}
