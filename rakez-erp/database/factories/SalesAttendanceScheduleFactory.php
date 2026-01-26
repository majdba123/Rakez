<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\SalesAttendanceSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesAttendanceScheduleFactory extends Factory
{
    protected $model = SalesAttendanceSchedule::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'user_id' => User::factory(),
            'schedule_date' => now()->addDays(rand(1, 30))->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'created_by' => User::factory(),
        ];
    }
}
