<?php

declare(strict_types=1);

use App\Models\AccountingSalaryDistribution;
use App\Models\AdminNotification;
use App\Models\AiCall;
use App\Models\AiCallScript;
use App\Models\AssistantConversation;
use App\Models\AssistantKnowledgeEntry;
use App\Models\ClaimFile;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\Conversation;
use App\Models\CreditFinancingTracker;
use App\Models\Deposit;
use App\Models\DeveloperMarketingPlan;
use App\Models\EmployeeContract;
use App\Models\EmployeeMarketingPlan;
use App\Models\EmployeeWarning;
use App\Models\ExclusiveProjectRequest;
use App\Models\Lead;
use App\Models\MarketingProject;
use App\Models\MarketingTask;
use App\Models\NegotiationApproval;
use App\Models\ReservationPaymentInstallment;
use App\Models\SalesAttendanceSchedule;
use App\Models\SalesReservation;
use App\Models\SalesTarget;
use App\Models\SalesWaitingList;
use App\Models\Task;
use App\Models\Team;
use App\Models\TitleTransfer;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function one(string $model, array $where = [])
{
    $q = $model::query();
    foreach ($where as $column => $value) {
        if (is_array($value)) {
            $q->whereIn($column, $value);
        } else {
            $q->where($column, $value);
        }
    }

    return $q->orderBy('id')->first();
}

function rid($model): string
{
    return $model ? (string) $model->id : '';
}

function vars_out(array $vars): array
{
    return array_map(
        fn ($k, $v) => ['key' => $k, 'value' => $v, 'type' => 'string'],
        array_keys($vars),
        array_values($vars)
    );
}

function jbody(array $payload): string
{
    return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function loginItem(string $name, string $emailVar, string $tokenVar): array
{
    return [
        'name' => $name,
        'event' => [[
            'listen' => 'test',
            'script' => [
                'type' => 'text/javascript',
                'exec' => [
                    'const data = pm.response.json();',
                    'if (data.access_token) {',
                    "pm.collectionVariables.set('auth_token', data.access_token);",
                    "pm.collectionVariables.set('{$tokenVar}', data.access_token);",
                    '}',
                ],
            ],
        ]],
        'request' => [
            'method' => 'POST',
            'header' => [
                ['key' => 'Accept', 'value' => 'application/json'],
                ['key' => 'Content-Type', 'value' => 'application/json'],
            ],
            'url' => [
                'raw' => '{{base_url}}/login',
                'host' => ['{{base_url}}'],
                'path' => ['login'],
            ],
            'body' => [
                'mode' => 'raw',
                'raw' => jbody(['email' => '{{' . $emailVar . '}}', 'password' => '{{default_password}}']),
                'options' => ['raw' => ['language' => 'json']],
            ],
        ],
        'response' => [],
    ];
}

function item(string $name, string $method, string $path, ?array $body = null): array
{
    $headers = [['key' => 'Accept', 'value' => 'application/json']];
    if ($body !== null) {
        $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
    }

    return [
        'name' => $name,
        'request' => [
            'method' => $method,
            'header' => $headers,
            'url' => [
                'raw' => '{{base_url}}/' . ltrim($path, '/'),
                'host' => ['{{base_url}}'],
                'path' => array_values(array_filter(explode('/', trim($path, '/')))),
            ],
            'auth' => [
                'type' => 'bearer',
                'bearer' => [['key' => 'token', 'value' => '{{auth_token}}', 'type' => 'string']],
            ],
            'body' => $body === null ? null : [
                'mode' => 'raw',
                'raw' => jbody($body),
                'options' => ['raw' => ['language' => 'json']],
            ],
        ],
        'response' => [],
    ];
}

$admin = one(User::class, ['email' => 'admin@rakez.com']);
$salesLeader = one(User::class, ['email' => 'sales.leader@rakez.com']);
$salesUser = one(User::class, ['email' => 'sales@rakez.com']);
$marketingUser = one(User::class, ['email' => 'marketing@rakez.com']);
$hrUser = one(User::class, ['email' => 'hr@rakez.com']);
$creditUser = one(User::class, ['email' => 'credit@rakez.com']);
$accountingUser = one(User::class, ['email' => 'accounting@rakez.com']);
$pmUser = one(User::class, ['email' => 'pm@rakez.com']);
$editorUser = one(User::class, ['email' => 'editor@rakez.com']);
$developerUser = one(User::class, ['email' => 'developer@rakez.com']);

$readyContract = one(Contract::class, ['status' => 'approved']);
$approvedContract = one(Contract::class, ['status' => 'approved']);
$pendingContract = one(Contract::class, ['status' => 'pending']);
$offPlanContract = Contract::where('status', 'approved')->where('is_off_plan', true)->orderBy('id')->first();
$baseContract = $readyContract ?? $approvedContract ?? Contract::orderBy('id')->first();
$baseContractId = rid($baseContract);

$unitQuery = fn (string $status) => ContractUnit::whereHas('secondPartyData', fn ($q) => $q->where('contract_id', $baseContractId))
    ->where('status', $status)->orderBy('id')->first();

$availableUnit = $baseContract ? $unitQuery('available') : null;
$reservedUnit = $baseContract ? $unitQuery('reserved') : null;
$soldUnit = $baseContract ? $unitQuery('sold') : null;

$confirmedReservation = one(SalesReservation::class, ['status' => 'confirmed']);
$negotiationReservation = one(SalesReservation::class, ['status' => 'under_negotiation']);
$cancelledReservation = one(SalesReservation::class, ['status' => 'cancelled']);
$bankConfirmedReservation = SalesReservation::where('status', 'confirmed')->whereIn('purchase_mechanism', ['supported_bank', 'unsupported_bank'])->orderBy('id')->first();
$conversation = one(Conversation::class);
$message = $conversation ? $conversation->messages()->orderBy('id')->first() : null;
$privateNotification = UserNotification::whereNotNull('user_id')->orderBy('id')->first();

$vars = [
    'base_url' => rtrim((string) config('app.url'), '/') . '/api',
    'auth_token' => '',
    'default_password' => 'password',
    'admin_email' => $admin?->email ?? 'admin@rakez.com',
    'sales_leader_email' => $salesLeader?->email ?? 'sales.leader@rakez.com',
    'sales_email' => $salesUser?->email ?? 'sales@rakez.com',
    'marketing_email' => $marketingUser?->email ?? 'marketing@rakez.com',
    'hr_email' => $hrUser?->email ?? 'hr@rakez.com',
    'credit_email' => $creditUser?->email ?? 'credit@rakez.com',
    'accounting_email' => $accountingUser?->email ?? 'accounting@rakez.com',
    'pm_email' => $pmUser?->email ?? 'pm@rakez.com',
    'editor_email' => $editorUser?->email ?? 'editor@rakez.com',
    'admin_token' => '',
    'sales_leader_token' => '',
    'sales_token' => '',
    'marketing_token' => '',
    'hr_token' => '',
    'credit_token' => '',
    'accounting_token' => '',
    'pm_token' => '',
    'editor_token' => '',
    'admin_user_id' => rid($admin),
    'sales_leader_user_id' => rid($salesLeader),
    'sales_user_id' => rid($salesUser),
    'marketing_user_id' => rid($marketingUser),
    'hr_user_id' => rid($hrUser),
    'credit_user_id' => rid($creditUser),
    'accounting_user_id' => rid($accountingUser),
    'pm_user_id' => rid($pmUser),
    'editor_user_id' => rid($editorUser),
    'developer_user_id' => rid($developerUser),
    'team_id' => rid(Team::orderBy('id')->first()),
    'base_contract_id' => $baseContractId,
    'ready_contract_id' => rid($readyContract),
    'approved_contract_id' => rid($approvedContract),
    'pending_contract_id' => rid($pendingContract),
    'off_plan_contract_id' => rid($offPlanContract),
    'developer_number' => (string) ($baseContract?->developer_number ?? ''),
    'available_unit_id' => rid($availableUnit),
    'reserved_unit_id' => rid($reservedUnit),
    'sold_unit_id' => rid($soldUnit),
    'confirmed_reservation_id' => rid($confirmedReservation),
    'negotiation_reservation_id' => rid($negotiationReservation),
    'cancelled_reservation_id' => rid($cancelledReservation),
    'bank_confirmed_reservation_id' => rid($bankConfirmedReservation),
    'sales_target_id' => rid(one(SalesTarget::class)),
    'attendance_id' => rid(one(SalesAttendanceSchedule::class)),
    'waiting_list_id' => rid(one(SalesWaitingList::class, ['status' => 'waiting'])),
    'negotiation_approval_id' => rid(one(NegotiationApproval::class)),
    'installment_id' => rid(one(ReservationPaymentInstallment::class)),
    'marketing_project_id' => rid(one(MarketingProject::class)),
    'developer_plan_id' => rid(one(DeveloperMarketingPlan::class)),
    'employee_plan_id' => rid(one(EmployeeMarketingPlan::class)),
    'marketing_task_id' => rid(one(MarketingTask::class)),
    'lead_id' => rid(one(Lead::class)),
    'employee_id' => rid(User::where('type', '!=', 'admin')->orderBy('id')->first()),
    'warning_id' => rid(one(EmployeeWarning::class)),
    'employee_contract_id' => rid(one(EmployeeContract::class)),
    'task_id' => rid(one(Task::class)),
    'conversation_id' => rid($conversation),
    'message_id' => rid($message),
    'private_notification_id' => rid($privateNotification),
    'public_notification_id' => rid(UserNotification::whereNull('user_id')->orderBy('id')->first()),
    'admin_notification_id' => rid(one(AdminNotification::class)),
    'commission_id' => rid(one(Commission::class)),
    'commission_distribution_id' => rid(one(CommissionDistribution::class)),
    'deposit_id' => rid(one(Deposit::class)),
    'salary_distribution_id' => rid(one(AccountingSalaryDistribution::class)),
    'financing_booking_id' => (string) (optional(one(CreditFinancingTracker::class))->sales_reservation_id ?? ''),
    'title_transfer_id' => rid(one(TitleTransfer::class)),
    'claim_file_id' => rid(one(ClaimFile::class)),
    'assistant_conversation_id' => rid(one(AssistantConversation::class)),
    'assistant_knowledge_id' => rid(one(AssistantKnowledgeEntry::class)),
    'ai_script_id' => rid(one(AiCallScript::class)),
    'ai_call_id' => rid(one(AiCall::class)),
    'exclusive_project_id' => rid(one(ExclusiveProjectRequest::class)),
    'today' => Carbon::now()->toDateString(),
    'future_date' => Carbon::now()->addDays(7)->toDateString(),
    'future_datetime' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
    'current_month' => (string) Carbon::now()->month,
    'current_year' => (string) Carbon::now()->year,
];

function placeholderPath(string $uri): string
{
    $replacements = [
        '{developer_number}' => '{{developer_number}}',
        '/contracts/show/{id}' => '/contracts/show/{{base_contract_id}}',
        '/contracts/update/{id}' => '/contracts/update/{{base_contract_id}}',
        '/contracts/{id}' => '/contracts/{{pending_contract_id}}',
        '/contracts/adminUpdateStatus/{id}' => '/contracts/adminUpdateStatus/{{base_contract_id}}',
        '/contracts/update-status/{id}' => '/contracts/update-status/{{base_contract_id}}',
        '/contracts/store/info/{id}' => '/contracts/store/info/{{base_contract_id}}',
        '/second-party-data/show/{id}' => '/second-party-data/show/{{base_contract_id}}',
        '/second-party-data/store/{id}' => '/second-party-data/store/{{base_contract_id}}',
        '/second-party-data/update/{id}' => '/second-party-data/update/{{base_contract_id}}',
        '/teams/show/{id}' => '/teams/show/{{team_id}}',
        '/project_management/teams/show/{id}' => '/project_management/teams/show/{{team_id}}',
        '/project_management/teams/update/{id}' => '/project_management/teams/update/{{team_id}}',
        '/project_management/teams/delete/{id}' => '/project_management/teams/delete/{{team_id}}',
        '/admin/employees/show_employee/{id}' => '/admin/employees/show_employee/{{employee_id}}',
        '/admin/employees/update_employee/{id}' => '/admin/employees/update_employee/{{employee_id}}',
        '/admin/employees/delete_employee/{id}' => '/admin/employees/delete_employee/{{employee_id}}',
        '/admin/employees/restore/{id}' => '/admin/employees/restore/{{employee_id}}',
        '/hr/show_employee/{id}' => '/hr/show_employee/{{employee_id}}',
        '/hr/update_employee/{id}' => '/hr/update_employee/{{employee_id}}',
        '/hr/delete_employee/{id}' => '/hr/delete_employee/{{employee_id}}',
        '/hr/marketers/{id}/performance' => '/hr/marketers/{{employee_id}}/performance',
        '{contractId}' => '{{ready_contract_id}}',
        '{unitId}' => '{{available_unit_id}}',
        '{unit_id}' => '{{sold_unit_id}}',
        '/sales/reservations/{id}' => '/sales/reservations/{{confirmed_reservation_id}}',
        '/sales/reservations/{id}/confirm' => '/sales/reservations/{{negotiation_reservation_id}}/confirm',
        '/sales/reservations/{id}/cancel' => '/sales/reservations/{{confirmed_reservation_id}}/cancel',
        '/sales/units/{id}/pdf' => '/sales/units/{{sold_unit_id}}/pdf',
        '/sales/marketing-tasks/{id}' => '/sales/marketing-tasks/{{marketing_task_id}}',
        '/sales/payment-installments/{id}' => '/sales/payment-installments/{{installment_id}}',
        '/sales/targets/{id}' => '/sales/targets/{{sales_target_id}}',
        '/sales/waiting-list/{id}' => '/sales/waiting-list/{{waiting_list_id}}',
        '/sales/negotiations/{id}' => '/sales/negotiations/{{negotiation_approval_id}}',
        '/my-tasks/{id}/status' => '/my-tasks/{{task_id}}/status',
        '{memberId}' => '{{sales_user_id}}',
        '{leadId}' => '{{lead_id}}',
        '{taskId}' => '{{marketing_task_id}}',
        '{planId}' => '{{employee_plan_id}}',
        '{projectId}' => '{{marketing_project_id}}',
        '{userId}' => '{{employee_id}}',
        '/hr/teams/{id}' => '/hr/teams/{{team_id}}',
        '/chat/conversations/{userId}' => '/chat/conversations/{{marketing_user_id}}',
        '/chat/conversations/{conversationId}' => '/chat/conversations/{{conversation_id}}',
        '{messageId}' => '{{message_id}}',
        '/accounting/sold-units/{id}' => '/accounting/sold-units/{{confirmed_reservation_id}}',
        '/accounting/commissions/{id}' => '/accounting/commissions/{{commission_id}}',
        '{distId}' => '{{commission_distribution_id}}',
        '/accounting/deposits/{id}' => '/accounting/deposits/{{deposit_id}}',
        '/accounting/confirmations/{id}' => '/accounting/confirmations/{{confirmed_reservation_id}}',
        '/accounting/salaries/{userId}' => '/accounting/salaries/{{employee_id}}',
        '{distributionId}' => '{{salary_distribution_id}}',
        '{reservationId}' => '{{confirmed_reservation_id}}',
        '/credit/bookings/negotiation/{id}' => '/credit/bookings/negotiation/{{negotiation_reservation_id}}',
        '/credit/bookings/{id}' => '/credit/bookings/{{bank_confirmed_reservation_id}}',
        '{bookingId}' => '{{financing_booking_id}}',
        '{stage}' => '2',
        '/credit/title-transfer/{id}' => '/credit/title-transfer/{{title_transfer_id}}',
        '/credit/claim-files/{id}' => '/credit/claim-files/{{claim_file_id}}',
        '/exclusive-projects/{id}' => '/exclusive-projects/{{exclusive_project_id}}',
        '/ai/knowledge/{id}' => '/ai/knowledge/{{assistant_knowledge_id}}',
        '/ai/calls/{id}' => '/ai/calls/{{ai_call_id}}',
        '/ai/calls/scripts/{id}' => '/ai/calls/scripts/{{ai_script_id}}',
        '{section}' => 'sales',
        '{key}' => 'default_cpm',
        '{sessionId}' => 'demo-session-id',
        '{callId}' => 'demo-call-id',
    ];

    $path = '/' . ltrim(preg_replace('#^api/#', '', $uri), '/');
    foreach ($replacements as $from => $to) {
        $path = str_replace($from, $to, $path);
    }

    if (str_contains($path, '/notifications/{id}/read')) {
        $path = str_replace('{id}', '{{private_notification_id}}', $path);
    }
    if (str_contains($path, '/hr/users/{id}')) {
        $path = str_replace('{id}', '{{employee_id}}', $path);
    }
    if (str_contains($path, '/hr/teams/{id}')) {
        $path = str_replace('{id}', '{{team_id}}', $path);
    }
    if (str_contains($path, '/marketing/employees/{id}')) {
        $path = str_replace('{id}', '{{employee_id}}', $path);
    }

    return $path;
}

function exampleBody(string $method, string $uri): ?array
{
    $key = $method . ' ' . $uri;
    $map = [
        'POST api/logout' => [],
        'POST api/contracts/store' => ['developer_name' => 'شركة التطوير التجريبية', 'developer_number' => '+966500000999', 'city_id' => 1, 'district_id' => 1, 'project_name' => 'مشروع واجهة الفرونت', 'developer_requiment' => 'رفع صور ومخططات وتسليم خلال 30 يوم', 'notes' => 'مثال جاهز', 'units' => [['type' => 'شقة', 'count' => 2, 'price' => 650000]]],
        'PUT api/contracts/update/{id}' => ['city_id' => 1, 'district_id' => 1, 'notes' => 'تحديث من بوستمان'],
        'POST api/contracts/store/info/{id}' => ['gregorian_date' => '{{today}}', 'contract_city' => 'الرياض', 'second_party_name' => 'شركة الطرف الثاني', 'second_party_email' => 'secondparty@example.com'],
        'POST api/second-party-data/store/{id}' => ['project_logo_url' => 'https://example.com/logo.png', 'marketing_license_url' => 'https://example.com/license.pdf', 'advertiser_section_url' => '125712612'],
        'PUT api/second-party-data/update/{id}' => ['project_logo_url' => 'https://example.com/logo-updated.png'],
        'PATCH api/contracts/update-status/{id}' => ['status' => 'approved'],
        'POST api/contracts/units/store/{contractId}' => ['unit_type' => 'شقة', 'unit_number' => 'UX-901', 'status' => 'available', 'price' => 790000, 'area' => '145'],
        'PUT api/contracts/units/update/{unitId}' => ['unit_type' => 'شقة', 'unit_number' => 'U-001', 'status' => 'reserved', 'price' => 780000],
        'POST api/boards-department/store/{contractId}' => ['has_ads' => true],
        'PUT api/boards-department/update/{contractId}' => ['has_ads' => false],
        'POST api/photography-department/store/{contractId}' => ['image_url' => 'https://example.com/photo.jpg', 'video_url' => 'https://example.com/photo.mp4', 'description' => 'مواد تصوير جديدة'],
        'PUT api/photography-department/update/{contractId}' => ['image_url' => 'https://example.com/photo-2.jpg', 'video_url' => 'https://example.com/photo-2.mp4'],
        'POST api/project_management/teams/store' => ['name' => 'فريق واجهة جديد', 'description' => 'فريق مخصص لتجربة الفرونت'],
        'PUT api/project_management/teams/update/{id}' => ['name' => 'فريق محدث', 'description' => 'تعديل الوصف'],
        'POST api/project_management/teams/add/{contractId}' => ['team_ids' => ['{{team_id}}']],
        'POST api/project_management/teams/remove/{contractId}' => ['team_ids' => ['{{team_id}}']],
        'POST api/editor/montage-department/store/{contractId}' => ['image_url' => 'https://example.com/montage.jpg', 'video_url' => 'https://example.com/montage.mp4'],
        'PUT api/editor/montage-department/update/{contractId}' => ['image_url' => 'https://example.com/montage-2.jpg', 'video_url' => 'https://example.com/montage-2.mp4'],
        'POST api/sales/reservations' => ['contract_id' => '{{ready_contract_id}}', 'contract_unit_id' => '{{available_unit_id}}', 'contract_date' => '{{today}}', 'reservation_type' => 'confirmed_reservation', 'client_name' => 'عميل فرونت تجريبي', 'client_mobile' => '0551000001', 'client_nationality' => 'Saudi', 'client_iban' => 'SA0380000000608010167519', 'payment_method' => 'cash', 'down_payment_amount' => 25000, 'down_payment_status' => 'refundable', 'purchase_mechanism' => 'cash'],
        'POST api/sales/reservations/{id}/cancel' => ['cancellation_reason' => 'طلب تجريبي من الفرونت'],
        'POST api/sales/reservations/{id}/actions' => ['action_type' => 'closing', 'notes' => 'تمت متابعة العميل'],
        'PATCH api/sales/targets/{id}' => ['status' => 'completed'],
        'PATCH api/sales/team/members/{memberId}/rating' => ['rating' => 5, 'notes' => 'ممتاز'],
        'PATCH api/sales/projects/{contractId}/emergency-contacts' => ['emergency_contact_number' => '0555555123', 'security_guard_number' => '0555555456'],
        'POST api/sales/targets' => ['marketer_id' => '{{employee_id}}', 'contract_id' => '{{ready_contract_id}}', 'contract_unit_id' => '{{available_unit_id}}', 'target_type' => 'reservation', 'start_date' => '{{today}}', 'end_date' => '{{future_date}}'],
        'POST api/sales/attendance/schedules' => ['contract_id' => '{{ready_contract_id}}', 'user_id' => '{{employee_id}}', 'schedule_date' => '{{future_date}}', 'start_time' => '09:00', 'end_time' => '17:00'],
        'POST api/sales/attendance/project/{contractId}/bulk' => ['schedules' => [['user_id' => '{{employee_id}}', 'schedule_date' => '{{future_date}}', 'start_time' => '09:00', 'end_time' => '17:00']]],
        'POST api/sales/marketing-tasks' => ['contract_id' => '{{ready_contract_id}}', 'task_name' => 'مهمة تسويقية', 'marketer_id' => '{{employee_id}}', 'status' => 'new'],
        'PATCH api/sales/marketing-tasks/{id}' => ['status' => 'in_progress'],
        'POST api/sales/waiting-list/' => ['contract_id' => '{{ready_contract_id}}', 'contract_unit_id' => '{{available_unit_id}}', 'client_name' => 'عميل قائمة انتظار', 'client_mobile' => '0558888888'],
        'POST api/sales/waiting-list/{id}/convert' => ['reservation_type' => 'confirmed_reservation'],
        'POST api/admin/notifications/send-to-user' => ['user_id' => '{{employee_id}}', 'message' => 'إشعار خاص من الأدمن'],
        'POST api/admin/notifications/send-public' => ['message' => 'إشعار عام من الأدمن'],
        'POST api/admin/sales/project-assignments' => ['leader_id' => '{{employee_id}}', 'contract_id' => '{{ready_contract_id}}', 'start_date' => '{{today}}', 'end_date' => '{{future_date}}'],
        'POST api/marketing/projects/calculate-budget' => ['contract_id' => '{{ready_contract_id}}', 'marketing_percent' => 8, 'marketing_value' => 70000, 'average_cpm' => 25, 'average_cpc' => 2.5, 'conversion_rate' => 3],
        'POST api/marketing/developer-plans' => ['contract_id' => '{{ready_contract_id}}', 'marketing_value' => 60000, 'average_cpm' => 24, 'average_cpc' => 2.4, 'conversion_rate' => 3.2, 'expected_bookings' => 18],
        'POST api/marketing/employee-plans' => ['marketing_project_id' => '{{marketing_project_id}}', 'user_id' => '{{employee_id}}'],
        'POST api/marketing/employee-plans/auto-generate' => ['marketing_project_id' => '{{marketing_project_id}}'],
        'PUT api/marketing/settings/conversion-rate' => ['value' => 4.25],
        'POST api/marketing/tasks' => ['contract_id' => '{{ready_contract_id}}', 'marketing_project_id' => '{{marketing_project_id}}', 'task_name' => 'إطلاق حملة ممولة', 'marketer_id' => '{{employee_id}}', 'status' => 'new'],
        'PUT api/marketing/tasks/{taskId}' => ['status' => 'in_progress'],
        'PATCH api/marketing/tasks/{taskId}/status' => ['status' => 'completed'],
        'POST api/marketing/projects/{projectId}/team' => ['user_ids' => ['{{employee_id}}']],
        'POST api/marketing/leads' => ['name' => 'عميل جديد من بوستمان', 'contact_info' => '0557777777', 'source' => 'snapchat', 'project_id' => '{{ready_contract_id}}', 'assigned_to' => '{{employee_id}}', 'status' => 'new'],
        'PUT api/marketing/leads/{leadId}' => ['status' => 'qualified', 'notes' => 'تم التأهيل'],
        'PUT api/marketing/settings/{key}' => ['value' => '27.50', 'description' => 'Updated from Postman'],
        'POST api/hr/users/' => ['name' => 'موظف جديد من HR', 'email' => 'new.hr.user@example.com', 'phone' => '0551111111', 'password' => 'password', 'type' => 1, 'salary' => 7000],
        'POST api/hr/users/{id}/contracts' => ['contract_data' => ['job_title' => 'مسوق عقاري', 'salary' => 7000], 'start_date' => '{{today}}', 'end_date' => '{{future_date}}', 'status' => 'draft'],
        'PATCH api/hr/users/{id}/status' => ['is_active' => true],
        'POST api/hr/users/{id}/warnings' => ['type' => 'performance', 'reason' => 'تأخر في الإنجاز', 'warning_date' => '{{today}}'],
        'PUT api/hr/users/{id}' => ['job_title' => 'قائد فريق', 'salary' => 9000, 'is_manager' => true],
        'POST api/hr/teams/' => ['name' => 'فريق HR جديد', 'description' => 'فريق تجريبي'],
        'POST api/hr/teams/{id}/members' => ['user_id' => '{{employee_id}}'],
        'PUT api/hr/teams/{id}' => ['name' => 'فريق محدث', 'description' => 'وصف محدث'],
        'POST api/accounting/sold-units/{id}/commission' => ['contract_unit_id' => '{{sold_unit_id}}', 'sales_reservation_id' => '{{confirmed_reservation_id}}', 'final_selling_price' => 950000, 'commission_percentage' => 2.5, 'commission_source' => 'owner'],
        'PUT api/accounting/commissions/{id}/distributions' => ['distributions' => [['user_id' => '{{employee_id}}', 'type' => 'closing', 'percentage' => 50], ['user_id' => '{{admin_user_id}}', 'type' => 'team_leader', 'percentage' => 50]]],
        'POST api/accounting/deposits/{id}/refund' => ['notes' => 'استرداد تجريبي'],
        'POST api/accounting/salaries/{userId}/distribute' => ['month' => '{{current_month}}', 'year' => '{{current_year}}'],
        'PATCH api/credit/bookings/negotiation/{id}' => ['notes' => 'تمت المراجعة'],
        'POST api/credit/bookings/{id}/cancel' => ['cancellation_reason' => 'إلغاء من Postman'],
        'POST api/credit/bookings/{id}/financing/advance' => ['bank_name' => 'بنك الراجحي', 'client_salary' => 18000, 'employment_type' => 'government'],
        'PATCH api/credit/bookings/{bookingId}/financing/stage/{stage}' => ['bank_name' => 'بنك الأهلي', 'client_salary' => 20000, 'employment_type' => 'private'],
        'POST api/credit/bookings/{bookingId}/financing/reject' => ['reason' => 'رفض من البنك'],
        'PATCH api/credit/title-transfer/{id}/schedule' => ['scheduled_date' => '{{future_date}}', 'notes' => 'موعد إفراغ'],
        'POST api/credit/claim-files/generate-bulk' => ['reservation_ids' => ['{{confirmed_reservation_id}}']],
        'POST api/credit/claim-files/combined' => ['booking_ids' => ['{{confirmed_reservation_id}}', '{{bank_confirmed_reservation_id}}'], 'claim_type' => 'commission', 'notes' => 'ملف مجمع'],
        'POST api/ai/ask' => ['question' => 'اعطني ملخص عن المشروع الحالي', 'section' => 'sales', 'context' => ['contract_id' => '{{ready_contract_id}}']],
        'POST api/ai/chat' => ['question' => 'ما هي حالة الحجوزات اليوم؟', 'section' => 'sales'],
        'POST api/ai/assistant/chat' => ['message' => 'اعطني ملخص سريع للمبيعات', 'module' => 'sales', 'page_key' => 'dashboard', 'language' => 'ar', 'conversation_id' => '{{assistant_conversation_id}}'],
        'POST api/ai/knowledge/' => ['title' => 'معلومة مساعدة', 'section' => 'sales', 'content' => 'هذه معلومة جديدة مخصصة للتجربة'],
        'PUT api/ai/knowledge/{id}' => ['title' => 'معلومة محدثة', 'content' => 'تم التحديث من Postman'],
        'POST api/ai/calls/initiate' => ['target_id' => '{{lead_id}}', 'target_type' => 'lead', 'script_id' => '{{ai_script_id}}'],
        'POST api/ai/calls/bulk' => ['target_ids' => ['{{lead_id}}'], 'target_type' => 'lead', 'script_id' => '{{ai_script_id}}'],
        'POST api/ai/calls/scripts' => ['name' => 'سكربت فرونت', 'target_type' => 'lead', 'language' => 'ar', 'greeting_text' => 'مرحبا {customer_name}', 'closing_text' => 'شكرا لوقتك', 'questions' => [['key' => 'budget', 'text_ar' => 'ما هي ميزانيتك؟']]],
        'PUT api/ai/calls/scripts/{id}' => ['name' => 'سكربت التأهيل المحدث', 'is_active' => true],
        'POST api/ads/sync' => ['platforms' => ['meta', 'snap', 'tiktok']],
        'POST api/ads/outcomes' => ['customer_id' => 'crm-{{lead_id}}', 'outcome_type' => 'qualified_lead', 'occurred_at' => '{{today}}', 'email' => 'lead@example.com', 'phone' => '0559000000', 'score' => 80, 'lead_id' => '{{lead_id}}', 'platforms' => ['meta', 'snap']],
    ];

    if (isset($map[$key])) {
        return $map[$key];
    }

    return in_array($method, ['POST', 'PUT', 'PATCH'], true) ? [] : null;
}

function folderName(string $uri): string
{
    $clean = preg_replace('#^api/#', '', $uri);
    $first = explode('/', $clean)[0] ?? '';

    return match ($first) {
        'contracts', 'second-party-data', 'boards-department', 'photography-department', 'project_management' => '02 Contracts And PM',
        'editor' => '03 Editor',
        'sales' => '04 Sales',
        'admin' => '05 Admin',
        'exclusive-projects' => '06 Exclusive Projects',
        'marketing' => '07 Marketing',
        'hr' => '08 HR',
        'inventory' => '09 Inventory',
        'accounting' => '10 Accounting',
        'credit' => '11 Credit',
        'ai', 'ads', 'webhooks' => '12 AI And Ads',
        default => '01 Shared',
    };
}

$folders = [
    '00 Auth' => [
        item('CSRF Token', 'GET', 'csrf-token'),
        loginItem('Login Admin', 'admin_email', 'admin_token'),
        loginItem('Login Sales Leader', 'sales_leader_email', 'sales_leader_token'),
        loginItem('Login Sales', 'sales_email', 'sales_token'),
        loginItem('Login Marketing', 'marketing_email', 'marketing_token'),
        loginItem('Login HR', 'hr_email', 'hr_token'),
        loginItem('Login Credit', 'credit_email', 'credit_token'),
        loginItem('Login Accounting', 'accounting_email', 'accounting_token'),
        loginItem('Login PM', 'pm_email', 'pm_token'),
        loginItem('Login Editor', 'editor_email', 'editor_token'),
    ],
    '01 Shared' => [],
    '02 Contracts And PM' => [],
    '03 Editor' => [],
    '04 Sales' => [],
    '05 Admin' => [],
    '06 Exclusive Projects' => [],
    '07 Marketing' => [],
    '08 HR' => [],
    '09 Inventory' => [],
    '10 Accounting' => [],
    '11 Credit' => [],
    '12 AI And Ads' => [],
];

foreach (Route::getRoutes() as $route) {
    $uri = $route->uri();
    if (!str_starts_with($uri, 'api/')) {
        continue;
    }
    if (in_array($uri, ['api/login', 'api/csrf-token'], true)) {
        continue;
    }

    foreach ($route->methods() as $method) {
        if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
            continue;
        }

        $path = ltrim(placeholderPath($uri), '/');
        $name = $method . ' ' . preg_replace('#^api/#', '', $uri);
        $folders[folderName($uri)][] = item($name, $method, $path, exampleBody($method, $uri));
    }
}

$collection = [
    'info' => [
        'name' => 'Rakez ERP Seeded Frontend Collection',
        '_postman_id' => (string) Str::uuid(),
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        'description' => 'Generated from routes and seeded records. Variables use real seeded IDs where available.',
    ],
    'variable' => vars_out($vars),
    'item' => array_map(fn ($folder, $items) => ['name' => $folder, 'item' => $items], array_keys($folders), array_values($folders)),
];

$json = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$outputs = [
    __DIR__ . '/Rakez_ERP_Seeded_Frontend.postman_collection.json',
    __DIR__ . '/Rakez_ERP_Seeded_Frontend.json',
];

foreach ($outputs as $output) {
    file_put_contents($output, $json);
    echo $output . PHP_EOL;
}
