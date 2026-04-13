<?php

declare(strict_types=1);

use App\Models\AccountingSalaryDistribution;
use App\Models\AdminNotification;
use App\Models\AiAuditEntry;
use App\Models\AiInteractionLog;
use App\Models\AssistantKnowledgeEntry;
use App\Models\ClaimFile;
use App\Models\CommissionDistribution;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\CreditFinancingTracker;
use App\Models\Deposit;
use App\Models\EmployeeContract;
use App\Models\EmployeePerformanceScore;
use App\Models\EmployeeWarning;
use App\Models\ExclusiveProjectRequest;
use App\Models\GovernanceAuditLog;
use App\Models\GovernanceTemporaryPermission;
use App\Models\Lead;
use App\Models\MarketingProject;
use App\Models\ProjectMedia;
use App\Models\SalesAttendanceSchedule;
use App\Models\SalesReservation;
use App\Models\SalesTarget;
use App\Models\Task;
use App\Models\Team;
use App\Models\TitleTransfer;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$databasePath = env('DB_DATABASE');

if (is_string($databasePath) && $databasePath !== '' && $databasePath !== ':memory:') {
    $directory = dirname($databasePath);

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    if (! file_exists($databasePath)) {
        touch($databasePath);
    }
}

$frozenNow = Carbon::parse((string) env('APP_FROZEN_NOW', '2030-01-01 09:00:00'));
Carbon::setTestNow($frozenNow);

Artisan::call('migrate:fresh', ['--force' => true]);
Artisan::call('db:seed', ['--class' => \Database\Seeders\RolesAndPermissionsSeeder::class, '--force' => true]);
app(PermissionRegistrar::class)->forgetCachedPermissions();

/**
 * @return array{id:int,email:string,password:string}
 */
function createGovernanceUser(string $role, string $email, string $name): array
{
    $password = 'password';

    $user = User::factory()->create([
        'name' => $name,
        'email' => $email,
        'password' => Hash::make($password),
        'type' => 'default',
        'is_active' => true,
    ]);

    $user->assignRole($role);

    return [
        'id' => $user->id,
        'email' => $email,
        'password' => $password,
    ];
}

$users = [
    'super_admin' => createGovernanceUser('super_admin', 'super-admin-visual@example.com', 'Super Admin Visual'),
    'erp_admin' => createGovernanceUser('erp_admin', 'erp-admin-visual@example.com', 'ERP Admin Visual'),
    'auditor_readonly' => createGovernanceUser('auditor_readonly', 'auditor-visual@example.com', 'Auditor Visual'),
    'credit_admin' => createGovernanceUser('credit_admin', 'credit-admin-visual@example.com', 'Credit Admin Visual'),
    'accounting_admin' => createGovernanceUser('accounting_admin', 'accounting-admin-visual@example.com', 'Accounting Admin Visual'),
    'projects_admin' => createGovernanceUser('projects_admin', 'projects-admin-visual@example.com', 'Projects Admin Visual'),
    'sales_admin' => createGovernanceUser('sales_admin', 'sales-admin-visual@example.com', 'Sales Admin Visual'),
    'hr_admin' => createGovernanceUser('hr_admin', 'hr-admin-visual@example.com', 'HR Admin Visual'),
    'marketing_admin' => createGovernanceUser('marketing_admin', 'marketing-admin-visual@example.com', 'Marketing Admin Visual'),
    'inventory_admin' => createGovernanceUser('inventory_admin', 'inventory-admin-visual@example.com', 'Inventory Admin Visual'),
    'ai_admin' => createGovernanceUser('ai_admin', 'ai-admin-visual@example.com', 'AI Admin Visual'),
    'workflow_admin' => createGovernanceUser('workflow_admin', 'workflow-admin-visual@example.com', 'Workflow Admin Visual'),
];

$noPanelUser = User::factory()->create([
    'name' => 'No Panel Visual',
    'email' => 'no-panel-visual@example.com',
    'password' => Hash::make('password'),
    'type' => 'sales',
    'is_active' => true,
]);
$noPanelUser->syncRolesFromType();

$erpAdmin = User::findOrFail($users['erp_admin']['id']);
$creditAdmin = User::findOrFail($users['credit_admin']['id']);
$accountingAdmin = User::findOrFail($users['accounting_admin']['id']);
$projectsAdmin = User::findOrFail($users['projects_admin']['id']);
$salesAdmin = User::findOrFail($users['sales_admin']['id']);
$hrAdmin = User::findOrFail($users['hr_admin']['id']);
$marketingAdmin = User::findOrFail($users['marketing_admin']['id']);
$aiAdmin = User::findOrFail($users['ai_admin']['id']);
$workflowAdmin = User::findOrFail($users['workflow_admin']['id']);

$team = Team::create([
    'code' => 'E2E-ADM',
    'name' => 'Admin Visual Team',
    'description' => 'Deterministic fixture team for Filament visual coverage.',
    'created_by' => $erpAdmin->id,
]);

$salesUser = User::factory()->create([
    'name' => 'Sales Fixture User',
    'email' => 'sales-fixture@example.com',
    'type' => 'sales',
    'team_id' => $team->id,
    'is_active' => true,
]);
$salesUser->syncRolesFromType();

$marketingUser = User::factory()->create([
    'name' => 'Marketing Fixture User',
    'email' => 'marketing-fixture@example.com',
    'type' => 'marketing',
    'team_id' => $team->id,
    'is_active' => true,
]);
$marketingUser->syncRolesFromType();

$hrUser = User::factory()->create([
    'name' => 'HR Fixture User',
    'email' => 'hr-fixture@example.com',
    'type' => 'hr',
    'team_id' => $team->id,
    'is_active' => true,
]);
$hrUser->syncRolesFromType();

$creditOpsUser = User::factory()->create([
    'name' => 'Credit Fixture User',
    'email' => 'credit-fixture@example.com',
    'type' => 'credit',
    'team_id' => $team->id,
    'is_active' => true,
]);
$creditOpsUser->syncRolesFromType();

$reviewUser = User::factory()->create([
    'name' => 'Review Target User',
    'email' => 'review-target@example.com',
    'type' => 'default',
    'team_id' => $team->id,
    'is_active' => true,
]);
$reviewUser->assignRole('workflow_admin');
$reviewUser->givePermissionTo(['notifications.view']);

$contract = Contract::factory()->create([
    'project_name' => 'Visual Governance Project',
    'status' => 'completed',
]);

$inventoryUnit = ContractUnit::factory()->create([
    'contract_id' => $contract->id,
    'unit_number' => 'VIS-101',
    'status' => 'available',
]);

$salesReservation = SalesReservation::factory()->create([
    'contract_id' => $contract->id,
    'contract_unit_id' => $inventoryUnit->id,
    'marketing_employee_id' => $salesUser->id,
    'status' => 'confirmed',
    'client_name' => 'Visual Sales Client',
    'credit_status' => 'pending',
    'purchase_mechanism' => 'cash',
]);

$creditFinancingReservation = SalesReservation::factory()->create([
    'contract_id' => $contract->id,
    'contract_unit_id' => ContractUnit::factory()->create([
        'contract_id' => $contract->id,
        'unit_number' => 'VIS-102',
        'status' => 'available',
    ])->id,
    'marketing_employee_id' => $salesUser->id,
    'status' => 'confirmed',
    'client_name' => 'Visual Credit Finance Client',
    'credit_status' => 'in_progress',
    'purchase_mechanism' => 'supported_bank',
    'payment_method' => 'bank',
]);

$creditTitleTransferReservation = SalesReservation::factory()->create([
    'contract_id' => $contract->id,
    'contract_unit_id' => ContractUnit::factory()->create([
        'contract_id' => $contract->id,
        'unit_number' => 'VIS-103',
        'status' => 'available',
    ])->id,
    'marketing_employee_id' => $salesUser->id,
    'status' => 'confirmed',
    'client_name' => 'Visual Credit Transfer Client',
    'credit_status' => 'title_transfer',
    'purchase_mechanism' => 'cash',
    'payment_method' => 'cash',
]);

$creditClaimReservation = SalesReservation::factory()->create([
    'contract_id' => $contract->id,
    'contract_unit_id' => ContractUnit::factory()->create([
        'contract_id' => $contract->id,
        'unit_number' => 'VIS-104',
        'status' => 'available',
    ])->id,
    'marketing_employee_id' => $salesUser->id,
    'status' => 'confirmed',
    'client_name' => 'Visual Credit Claim Client',
    'credit_status' => 'sold',
    'purchase_mechanism' => 'cash',
    'payment_method' => 'cash',
]);

$creditTracker = CreditFinancingTracker::factory()->create([
    'sales_reservation_id' => $creditFinancingReservation->id,
    'assigned_to' => $creditAdmin->id,
    'overall_status' => 'in_progress',
    'stage_1_status' => 'completed',
    'stage_2_status' => 'in_progress',
    'stage_3_status' => 'pending',
    'stage_4_status' => 'pending',
    'stage_5_status' => 'pending',
]);

$titleTransfer = TitleTransfer::factory()->create([
    'sales_reservation_id' => $creditTitleTransferReservation->id,
    'processed_by' => $creditAdmin->id,
    'status' => 'scheduled',
    'scheduled_date' => $frozenNow->copy()->addDays(2)->toDateString(),
    'notes' => 'Visual transfer review fixture.',
]);

$claimFile = ClaimFile::factory()->create([
    'sales_reservation_id' => $creditClaimReservation->id,
    'generated_by' => $creditAdmin->id,
    'pdf_path' => 'claim_files/visual-fixture.pdf',
    'total_claim_amount' => 150000,
]);

$creditNotification = UserNotification::query()->create([
    'user_id' => $creditOpsUser->id,
    'message' => 'Credit review notification',
    'status' => 'pending',
    'event_type' => 'credit_review',
]);

$deposit = Deposit::factory()->create([
    'contract_id' => $contract->id,
    'contract_unit_id' => $inventoryUnit->id,
    'sales_reservation_id' => $salesReservation->id,
    'client_name' => 'Visual Deposit Client',
    'status' => 'pending',
    'commission_source' => 'owner',
]);

$commissionDistribution = CommissionDistribution::factory()->create([
    'user_id' => $salesUser->id,
    'status' => 'pending',
    'type' => 'lead_generation',
]);

$salaryDistribution = AccountingSalaryDistribution::factory()->pending()->create([
    'user_id' => $salesUser->id,
]);

$exclusiveProjectRequest = ExclusiveProjectRequest::factory()->create([
    'requested_by' => $salesUser->id,
    'project_name' => 'Visual Exclusive Project',
    'status' => 'pending',
]);

$projectMedia = ProjectMedia::create([
    'contract_id' => $contract->id,
    'type' => 'image',
    'url' => 'https://example.com/visual-project.jpg',
    'department' => 'montage',
]);

$salesTarget = SalesTarget::query()->create([
    'contract_id' => $contract->id,
    'contract_unit_id' => $inventoryUnit->id,
    'leader_id' => $salesUser->id,
    'marketer_id' => $marketingUser->id,
    'target_type' => 'reservation',
    'start_date' => $frozenNow->copy()->startOfMonth()->toDateString(),
    'end_date' => $frozenNow->copy()->endOfMonth()->toDateString(),
    'status' => 'in_progress',
    'leader_notes' => 'Visual sales target fixture',
]);

$attendanceSchedule = SalesAttendanceSchedule::factory()->create([
    'contract_id' => $contract->id,
    'user_id' => $salesUser->id,
    'created_by' => $salesAdmin->id,
]);

$employeePerformanceScore = EmployeePerformanceScore::query()->create([
    'user_id' => $hrUser->id,
    'composite_score' => 91.5,
    'factor_scores' => ['quality' => 95],
    'strengths' => ['follow_up'],
    'weaknesses' => ['lateness'],
    'project_type_affinity' => ['residential'],
    'period_start' => $frozenNow->copy()->startOfMonth()->toDateString(),
    'period_end' => $frozenNow->copy()->endOfMonth()->toDateString(),
]);

$employeeWarning = EmployeeWarning::factory()->create([
    'user_id' => $hrUser->id,
    'issued_by' => $hrAdmin->id,
    'reason' => 'Visual HR warning fixture',
]);

$employeeContract = EmployeeContract::factory()->create([
    'user_id' => $hrUser->id,
    'status' => 'active',
]);

$marketingProject = MarketingProject::factory()->create([
    'contract_id' => $contract->id,
    'assigned_team_leader' => $marketingUser->id,
]);

\App\Models\DeveloperMarketingPlan::factory()->create([
    'contract_id' => $contract->id,
]);

\App\Models\EmployeeMarketingPlan::factory()->create([
    'marketing_project_id' => $marketingProject->id,
    'user_id' => $marketingUser->id,
]);

\App\Models\MarketingTask::factory()->create([
    'contract_id' => $contract->id,
    'marketing_project_id' => $marketingProject->id,
    'marketer_id' => $marketingUser->id,
    'created_by' => $marketingAdmin->id,
    'task_name' => 'Visual Marketing Task',
]);

$lead = Lead::factory()->create([
    'project_id' => $contract->id,
    'assigned_to' => $marketingUser->id,
    'name' => 'Visual Lead',
    'status' => 'new',
]);

$knowledgeEntry = AssistantKnowledgeEntry::query()->create([
    'module' => 'admin',
    'page_key' => 'visual-review',
    'title' => 'Visual Knowledge Entry',
    'content_md' => 'Visual governance content.',
    'tags' => ['visual', 'governance'],
    'roles' => ['ai_admin'],
    'permissions' => ['ai.knowledge.view'],
    'language' => 'ar',
    'is_active' => true,
    'priority' => 10,
    'updated_by' => $aiAdmin->id,
]);

$interactionLog = AiInteractionLog::query()->create([
    'user_id' => $aiAdmin->id,
    'session_id' => 'visual-session',
    'correlation_id' => 'visual-correlation',
    'section' => 'admin',
    'request_type' => 'qa',
    'model' => 'gpt-visual',
    'prompt_tokens' => 20,
    'completion_tokens' => 40,
    'total_tokens' => 60,
    'latency_ms' => 120.25,
    'tool_calls_count' => 1,
    'had_error' => false,
    'created_at' => $frozenNow,
]);

$aiAuditEntry = AiAuditEntry::query()->create([
    'user_id' => $aiAdmin->id,
    'correlation_id' => 'visual-correlation',
    'action' => 'knowledge_view',
    'resource_type' => 'assistant_knowledge_entry',
    'resource_id' => $knowledgeEntry->id,
    'input_summary' => 'visual input',
    'output_summary' => 'visual output',
    'ip_address' => '127.0.0.1',
    'created_at' => $frozenNow,
]);

$workflowTask = Task::query()->create([
    'task_name' => 'Visual Workflow Task',
    'section' => 'workflow',
    'team_id' => $team->id,
    'due_at' => $frozenNow->copy()->addDay(),
    'assigned_to' => $workflowAdmin->id,
    'status' => Task::STATUS_IN_PROGRESS,
    'created_by' => $erpAdmin->id,
]);

$adminNotification = AdminNotification::query()->create([
    'user_id' => $erpAdmin->id,
    'message' => 'Visual admin notification',
    'status' => 'pending',
]);

$userNotification = UserNotification::query()->create([
    'user_id' => $workflowAdmin->id,
    'message' => 'Visual workflow notification',
    'status' => 'pending',
    'event_type' => 'workflow_pending',
]);

$governanceAuditLog = GovernanceAuditLog::query()->create([
    'actor_id' => $erpAdmin->id,
    'event' => 'governance.visual.fixture.seeded',
    'subject_type' => User::class,
    'subject_id' => $reviewUser->id,
    'payload' => [
        'before' => ['panel_access' => false],
        'after' => ['panel_access' => true],
        'ip_address' => '127.0.0.1',
    ],
]);

$governanceTemporaryPermission = GovernanceTemporaryPermission::query()->create([
    'user_id' => $reviewUser->id,
    'permission' => 'admin.dashboard.view',
    'granted_by_id' => $erpAdmin->id,
    'reason' => 'E2E visual fixture (temporary governance grant)',
    'expires_at' => $frozenNow->copy()->addDay(),
]);

$superAdminRole = Role::query()->where('name', 'super_admin')->firstOrFail();
$workflowRole = Role::query()->where('name', 'workflow_admin')->firstOrFail();

$manifest = [
    'base_url' => env('APP_URL', 'http://127.0.0.1:8001'),
    'credentials' => array_merge($users, [
        'no_panel_user' => [
            'id' => $noPanelUser->id,
            'email' => $noPanelUser->email,
            'password' => 'password',
        ],
    ]),
    'records' => [
        'review_user_id' => $reviewUser->id,
        'super_admin_role_id' => $superAdminRole->id,
        'workflow_role_id' => $workflowRole->id,
        'governance_audit_log_id' => $governanceAuditLog->id,
        'governance_temporary_permission_id' => $governanceTemporaryPermission->id,
        'effective_access_user_id' => $reviewUser->id,
        'credit_booking_id' => $creditFinancingReservation->id,
        'title_transfer_id' => $titleTransfer->id,
        'claim_file_id' => $claimFile->id,
        'credit_notification_id' => $creditNotification->id,
        'deposit_id' => $deposit->id,
        'commission_distribution_id' => $commissionDistribution->id,
        'salary_distribution_id' => $salaryDistribution->id,
        'contract_id' => $contract->id,
        'exclusive_project_request_id' => $exclusiveProjectRequest->id,
        'project_media_id' => $projectMedia->id,
        'sales_reservation_id' => $salesReservation->id,
        'sales_target_id' => $salesTarget->id,
        'attendance_schedule_id' => $attendanceSchedule->id,
        'employee_performance_score_id' => $employeePerformanceScore->id,
        'employee_warning_id' => $employeeWarning->id,
        'employee_contract_id' => $employeeContract->id,
        'marketing_project_id' => $marketingProject->id,
        'lead_id' => $lead->id,
        'inventory_unit_id' => $inventoryUnit->id,
        'knowledge_entry_id' => $knowledgeEntry->id,
        'ai_interaction_log_id' => $interactionLog->id,
        'ai_audit_entry_id' => $aiAuditEntry->id,
        'workflow_task_id' => $workflowTask->id,
        'admin_notification_id' => $adminNotification->id,
        'user_notification_id' => $userNotification->id,
        'team_id' => $team->id,
        'credit_tracker_id' => $creditTracker->id,
    ],
];

file_put_contents(
    storage_path('app/e2e-admin-visual-manifest.json'),
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "Admin visual fixtures ready.\n";
