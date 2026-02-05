<?php

namespace Database\Seeders;

class SeedCounts
{
    public static function all(): array
    {
        return [
            'teams' => 8,
            'users_total' => 185,
            'contracts' => 50,
            'units_per_contract' => 15,
            'marketing_projects' => 20,
            'employee_marketing_plans_per_project' => 4,
            'campaigns_per_plan' => 2,
            'leads_per_contract' => 6,
            'marketing_tasks_per_contract' => 2,
            'daily_deposits_per_contract' => 10,
            'daily_marketing_spends' => 60,
            'sales_reservations' => 300,
            'sales_targets' => 120,
            'attendance_schedules' => 200,
            'waiting_list_entries' => 80,
            'negotiation_approvals' => 120,
            'installments_per_offplan_confirmed' => 4,
            'commissions' => 150,
            'deposits' => 200,
            'salary_distributions' => 120,
            'employee_warnings' => 80,
            'assistant_conversations' => 10,
            'assistant_messages_min' => 3,
            'assistant_messages_max' => 5,
            'admin_notifications' => 20,
            'user_notifications' => 50,
        ];
    }
}
