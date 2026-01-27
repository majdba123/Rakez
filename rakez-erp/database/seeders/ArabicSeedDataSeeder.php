<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SecondPartyData;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ArabicSeedDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Users with Arabic Names
        $salesLeader = User::create([
            'name' => 'أحمد القحطاني',
            'email' => 'ahmed@rakez.com',
            'password' => Hash::make('password'),
            'type' => 'sales_leader',
        ]);

        $marketer = User::create([
            'name' => 'سارة الشمري',
            'email' => 'sara@rakez.com',
            'password' => Hash::make('password'),
            'type' => 'marketing',
        ]);

        $aiUser = User::create([
            'name' => 'خالد العتيبي',
            'email' => 'khaled@rakez.com',
            'password' => Hash::make('password'),
            'type' => 'user',
        ]);

        // 2. Create Contracts (Projects) with Arabic Titles
        $projects = [
            [
                'project_name' => 'برج ركيز السكني 1',
                'developer_name' => 'شركة ركيز العقارية',
                'developer_number' => 'DEV-001',
                'city' => 'الرياض',
                'district' => 'حي الملقا',
                'status' => 'approved',
                'notes' => 'مشروع سكني فاخر يضم وحدات متنوعة',
                'developer_requiment' => 'متطلبات المطور تشمل الجودة العالية في التشطيب',
            ],
            [
                'project_name' => 'مجمع الياسمين السكني',
                'developer_name' => 'تطوير العقار المحدودة',
                'developer_number' => 'DEV-002',
                'city' => 'جدة',
                'district' => 'حي الشاطئ',
                'status' => 'approved',
                'notes' => 'مجمع سكني متكامل الخدمات',
                'developer_requiment' => 'التركيز على المساحات الخضراء',
            ],
            [
                'project_name' => 'فلل النرجس الفاخرة',
                'developer_name' => 'نخبة المطورين',
                'developer_number' => 'DEV-003',
                'city' => 'الدمام',
                'district' => 'حي النزهة',
                'status' => 'approved',
                'notes' => 'فلل سكنية بتصاميم عصرية',
                'developer_requiment' => 'تصاميم معمارية فريدة',
            ],
        ];

        foreach ($projects as $projectData) {
            $contract = Contract::create(array_merge($projectData, [
                'user_id' => $salesLeader->id,
                'emergency_contact_number' => '0500000001',
                'security_guard_number' => '0500000002',
            ]));

            // Create Second Party Data for each contract
            $secondParty = SecondPartyData::create([
                'contract_id' => $contract->id,
                'project_logo_url' => 'https://via.placeholder.com/150',
            ]);

            // 3. Create Contract Units with Arabic Details
            for ($i = 1; $i <= 5; $i++) {
                ContractUnit::create([
                    'second_party_data_id' => $secondParty->id,
                    'unit_type' => $i % 2 == 0 ? 'شقة' : 'فيلا',
                    'unit_number' => 'U-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'floor' => (string)rand(1, 10),
                    'area' => rand(150, 400),
                    'price' => rand(500000, 2000000),
                    'status' => 'available',
                    'description' => 'وحدة سكنية واسعة مع إطلالة رائعة وتشطيبات ممتازة',
                ]);
            }

            // 4. Sales Data
            // Sales Project Assignment
            DB::table('sales_project_assignments')->insert([
                'contract_id' => $contract->id,
                'leader_id' => $salesLeader->id,
                'assigned_by' => $salesLeader->id, // Assuming self-assigned for seed data
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Sales Targets
            DB::table('sales_targets')->insert([
                'leader_id' => $salesLeader->id,
                'marketer_id' => $marketer->id,
                'contract_id' => $contract->id,
                'target_type' => 'reservation',
                'start_date' => Carbon::now()->startOfMonth(),
                'end_date' => Carbon::now()->addMonths(3)->endOfMonth(),
                'status' => 'in_progress',
                'leader_notes' => 'هدف مبيعات الربع الأول لعام 2026',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Sales Attendance
            DB::table('sales_attendance_schedules')->insert([
                'user_id' => $marketer->id,
                'contract_id' => $contract->id,
                'schedule_date' => Carbon::today(),
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'created_by' => $salesLeader->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Marketing Data
        $marketingProject = DB::table('marketing_projects')->insertGetId([
            'contract_id' => Contract::first()->id,
            'status' => 'active',
            'assigned_team_leader' => $salesLeader->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('marketing_project_teams')->insert([
            'marketing_project_id' => $marketingProject,
            'user_id' => $marketer->id,
            'role' => 'marketer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Developer Marketing Plans
        DB::table('developer_marketing_plans')->insert([
            'contract_id' => Contract::first()->id,
            'average_cpm' => 25.00,
            'average_cpc' => 2.50,
            'marketing_value' => 35000.00,
            'expected_impressions' => 1400000,
            'expected_clicks' => 14000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Employee Marketing Plans
        $employeePlanId = DB::table('employee_marketing_plans')->insertGetId([
            'marketing_project_id' => $marketingProject,
            'user_id' => $marketer->id,
            'commission_value' => 5000.00,
            'marketing_value' => 15000.00,
            'platform_distribution' => json_encode(['snapchat' => 50, 'instagram' => 50]),
            'campaign_distribution' => json_encode(['awareness' => 40, 'conversion' => 60]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Marketing Campaigns
        DB::table('marketing_campaigns')->insert([
            [
                'employee_marketing_plan_id' => $employeePlanId,
                'platform' => 'snapchat',
                'campaign_type' => 'conversion',
                'budget' => 7500.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'employee_marketing_plan_id' => $employeePlanId,
                'platform' => 'instagram',
                'campaign_type' => 'awareness',
                'budget' => 7500.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Leads
        DB::table('leads')->insert([
            [
                'name' => 'محمد العلي',
                'contact_info' => '0555555555',
                'source' => 'سناب شات',
                'status' => 'new',
                'project_id' => Contract::first()->id,
                'assigned_to' => $marketer->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'فاطمة الزهراني',
                'contact_info' => 'fatima@example.com',
                'source' => 'انستقرام',
                'status' => 'contacted',
                'project_id' => Contract::first()->id,
                'assigned_to' => $marketer->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Marketing Tasks
        DB::table('marketing_tasks')->insert([
            [
                'contract_id' => Contract::first()->id,
                'marketing_project_id' => $marketingProject,
                'task_name' => 'إطلاق حملة سناب شات',
                'marketer_id' => $marketer->id,
                'participating_marketers_count' => 2,
                'status' => 'new',
                'due_date' => Carbon::tomorrow(),
                'created_by' => $salesLeader->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'contract_id' => Contract::first()->id,
                'marketing_project_id' => $marketingProject,
                'task_name' => 'تحديث قائمة الأسعار في الموقع',
                'marketer_id' => $marketer->id,
                'participating_marketers_count' => 1,
                'status' => 'in_progress',
                'due_date' => Carbon::now()->addDays(2),
                'created_by' => $salesLeader->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Sales Reservations
        DB::table('sales_reservations')->insert([
            'marketing_employee_id' => $marketer->id,
            'contract_id' => Contract::first()->id,
            'contract_unit_id' => ContractUnit::first()->id,
            'reservation_type' => 'negotiation',
            'contract_date' => Carbon::today(),
            'client_name' => 'عبدالله المنصور',
            'client_mobile' => '0566666666',
            'client_nationality' => 'سعودي',
            'client_iban' => 'SA1234567890123456789012',
            'payment_method' => 'bank_transfer',
            'down_payment_amount' => 10000.00,
            'down_payment_status' => 'refundable',
            'purchase_mechanism' => 'cash',
            'negotiation_notes' => 'العميل مهتم بالدور العلوي',
            'status' => 'under_negotiation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6. AI Assistant Data
        $sessionId = \Illuminate\Support\Str::uuid();
        DB::table('ai_conversations')->insert([
            [
                'user_id' => $aiUser->id,
                'session_id' => $sessionId,
                'role' => 'user',
                'message' => 'ما هي الوحدات المتاحة في مشروع برج ركيز؟',
                'section' => 'units',
                'metadata' => json_encode(['project_id' => Contract::first()->id]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $aiUser->id,
                'session_id' => $sessionId,
                'role' => 'assistant',
                'message' => 'يوجد حالياً 5 وحدات متاحة في مشروع برج ركيز السكني 1، تتنوع بين شقق وفلل بمساحات تبدأ من 150 متر مربع.',
                'section' => 'units',
                'metadata' => json_encode(['project_id' => Contract::first()->id]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $aiUser->id,
                'session_id' => $sessionId,
                'role' => 'user',
                'message' => 'كم تبلغ أسعار الفلل؟',
                'section' => 'units',
                'metadata' => json_encode(['project_id' => Contract::first()->id]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $aiUser->id,
                'session_id' => $sessionId,
                'role' => 'assistant',
                'message' => 'تبدأ أسعار الفلل في المشروع من 1,200,000 ريال سعودي حسب المساحة والموقع.',
                'section' => 'units',
                'metadata' => json_encode(['project_id' => Contract::first()->id]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
