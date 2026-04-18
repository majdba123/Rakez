# Filament Thin Console Audit

## Scope
- Filament 5.4 backend admin only (no SPA repository assumptions).
- Writable Filament actions audited against shared service/domain paths.

## Writable Action Classification Summary
- ALREADY SHARED WITH LEGACY/API: 71
- THIN WRAPPER: 9

## Writable Action Matrix
| File | Action | Service Path | Classification |
|---|---|---|---|
| app\Filament\Admin\Resources\AccountingClaimFiles\Pages\ListAccountingClaimFiles.php | generateBulk | ClaimFileService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\AccountingClaimFiles\Pages\ListAccountingClaimFiles.php | generateCombined | ClaimFileService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\AccountingDeposits\AccountingDepositResource.php | confirmDeposit | AccountingDepositService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\AccountingDeposits\AccountingDepositResource.php | processRefund | AccountingDepositService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\AccountingNotifications\AccountingNotificationResource.php | markRead | AccountingNotificationService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\AccountingNotifications\Pages\ListAccountingNotifications.php | markAllRead | AccountingNotificationService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\AdminNotifications\AdminNotificationResource.php | markRead | NotificationAdminService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\AdminNotifications\Pages\ListAdminNotifications.php | markAllRead | NotificationAdminService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\AdminNotifications\Pages\ListAdminNotifications.php | sendAdminNotification | NotificationAdminService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\AssistantKnowledgeEntries\AssistantKnowledgeEntryResource.php | deleteEntry | AssistantKnowledgeEntryService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages\CreateAssistantKnowledgeEntry.php | handleRecordCreation | AssistantKnowledgeEntryService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages\EditAssistantKnowledgeEntry.php | deleteEntry | AssistantKnowledgeEntryService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages\EditAssistantKnowledgeEntry.php | handleRecordUpdate | AssistantKnowledgeEntryService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\ClaimFiles\ClaimFileResource.php | generatePdf | ClaimFileService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\ClaimFiles\Pages\ListClaimFiles.php | generateBulk | ClaimFileService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\ClaimFiles\Pages\ListClaimFiles.php | generateCombined | ClaimFileService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\CommissionDistributions\CommissionDistributionResource.php | approveDistribution | AccountingCommissionService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\CommissionDistributions\CommissionDistributionResource.php | markPaid | AccountingCommissionService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\CommissionDistributions\CommissionDistributionResource.php | rejectDistribution | AccountingCommissionService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\Contracts\ContractResource.php | approveContract | ContractService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\Contracts\ContractResource.php | markReadyForMarketing | ContractService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\Contracts\ContractResource.php | rejectContract | ContractService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | advanceFinancing | CreditFinancingService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | cancelBooking | SalesReservationService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | editClient | SalesReservationService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | generateClaimFile | ClaimFileService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | generateClaimPdf | ClaimFileService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | logClientContact | SalesReservationService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | rejectFinancing | CreditFinancingService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\CreditNotifications\CreditNotificationResource.php | markRead | CreditNotificationService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\CreditNotifications\Pages\ListCreditNotifications.php | markAllRead | CreditNotificationService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\DirectPermissions\Pages\EditDirectPermissions.php | handleRecordUpdate | DirectPermissionGovernanceService::class | THIN WRAPPER |
| app\Filament\Admin\Resources\EmployeeContracts\EmployeeContractResource.php | activateContract | EmployeeContractService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\EmployeeContracts\EmployeeContractResource.php | editContract | EmployeeContractService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\EmployeeContracts\EmployeeContractResource.php | generatePdf | EmployeeContractService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\EmployeeContracts\EmployeeContractResource.php | terminateContract | EmployeeContractService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\EmployeeContracts\Pages\ListEmployeeContracts.php | createContract | EmployeeContractService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\EmployeeWarnings\EmployeeWarningResource.php | deleteWarning | EmployeeWarningService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\EmployeeWarnings\Pages\ListEmployeeWarnings.php | issueWarning | EmployeeWarningService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\ExclusiveProjectRequests\ExclusiveProjectRequestResource.php | approveRequest | ExclusiveProjectService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\ExclusiveProjectRequests\ExclusiveProjectRequestResource.php | completeContract | ExclusiveProjectService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\ExclusiveProjectRequests\ExclusiveProjectRequestResource.php | exportContract | ExclusiveProjectService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\ExclusiveProjectRequests\ExclusiveProjectRequestResource.php | rejectRequest | ExclusiveProjectService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\GovernanceTemporaryPermissions\GovernanceTemporaryPermissionResource.php | revoke | GovernanceTemporaryPermissionService::class | THIN WRAPPER |
| app\Filament\Admin\Resources\GovernanceTemporaryPermissions\Pages\CreateGovernanceTemporaryPermission.php | handleRecordCreation | GovernanceTemporaryPermissionService::class | THIN WRAPPER |
| app\Filament\Admin\Resources\HrTeams\HrTeamResource.php | deleteTeam | TeamService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\HrTeams\Pages\CreateHrTeam.php | handleRecordCreation | TeamService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\HrTeams\Pages\EditHrTeam.php | deleteTeam | TeamService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\HrTeams\Pages\EditHrTeam.php | handleRecordUpdate | TeamService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\InventoryUnits\InventoryUnitResource.php | deleteUnit | ContractUnitService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\InventoryUnits\Pages\EditInventoryUnit.php | deleteUnit | ContractUnitService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\InventoryUnits\Pages\EditInventoryUnit.php | handleRecordUpdate | ContractUnitService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\MarketingTasks\MarketingTaskResource.php | deleteTask | MarketingTaskService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\MarketingTasks\MarketingTaskResource.php | markCompleted | MarketingTaskService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\MarketingTasks\Pages\CreateMarketingTask.php | handleRecordCreation | MarketingTaskService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\MarketingTasks\Pages\EditMarketingTask.php | deleteTask | MarketingTaskService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\MarketingTasks\Pages\EditMarketingTask.php | handleRecordUpdate | MarketingTaskService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\Roles\Pages\EditRole.php | handleRecordUpdate | RoleGovernanceService::class | THIN WRAPPER |
| app\Filament\Admin\Resources\SalaryDistributions\SalaryDistributionResource.php | approveSalary | AccountingSalaryService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\SalaryDistributions\SalaryDistributionResource.php | markSalaryPaid | AccountingSalaryService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\SalesAttendanceSchedules\SalesAttendanceScheduleResource.php | deleteSchedule | SalesAttendanceService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\SalesReservations\SalesReservationResource.php | cancelReservation | SalesReservationService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\SalesReservations\SalesReservationResource.php | confirmReservation | SalesReservationService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\SalesTargets\SalesTargetResource.php | updateTargetStatus | SalesTargetService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\TitleTransfers\TitleTransferResource.php | completeTransfer | TitleTransferService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\TitleTransfers\TitleTransferResource.php | scheduleTransfer | TitleTransferService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\TitleTransfers\TitleTransferResource.php | unscheduleTransfer | TitleTransferService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\UserNotifications\Pages\ListUserNotifications.php | markAllRead | NotificationAdminService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\UserNotifications\Pages\ListUserNotifications.php | sendPublicNotification | NotificationAdminService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\UserNotifications\Pages\ListUserNotifications.php | sendUserNotification | NotificationAdminService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\UserNotifications\UserNotificationResource.php | markRead | NotificationAdminService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\Users\Pages\CreateUser.php | handleRecordCreation | UserGovernanceService::class | THIN WRAPPER |
| app\Filament\Admin\Resources\Users\Pages\EditUser.php | deleteUser | UserGovernanceService::class | THIN WRAPPER |
| app\Filament\Admin\Resources\Users\Pages\EditUser.php | handleRecordUpdate | UserGovernanceService::class | THIN WRAPPER |
| app\Filament\Admin\Resources\Users\UserResource.php | deleteUser | UserGovernanceService::class | THIN WRAPPER |
| app\Filament\Admin\Resources\Users\UserResource.php | restoreUser | UserGovernanceService::class | THIN WRAPPER |
| app\Filament\Admin\Resources\WorkflowTasks\Pages\ListWorkflowTasks.php | createTask | WorkflowTaskAdminService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\WorkflowTasks\WorkflowTaskResource.php | markCompleted | WorkflowTaskAdminService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\WorkflowTasks\WorkflowTaskResource.php | markCouldNotComplete | WorkflowTaskAdminService::class | ALREADY SHARED WITH LEGACY/API |
| app\Filament\Admin\Resources\WorkflowTasks\WorkflowTaskResource.php | markInProgress | WorkflowTaskAdminService::class | ALREADY SHARED WITH LEGACY/API |

## Notes
- `ALREADY SHARED WITH LEGACY/API` means the same service class is also referenced in non-Filament controller flows.
- `THIN WRAPPER` means Filament writes through governance/admin services not currently reused by API controllers (governance-only domain surface).
- No `DUPLICATED LOGIC` entries were confirmed in this audited writable matrix.

## Governance Authority Clarification
- `CONFIRMED`: Filament panel entry is top-authority-only via `GovernanceAccessService::canAccessPanel()` and `config('governance.panel_authority_roles')`.
- `CONFIRMED`: Section governance roles (`credit_admin`, `workflow_admin`, `accounting_admin`, etc.) are managed through `config('governance.managed_governance_roles')` for governance/service scope, not for panel entry.
- `CONFIRMED`: Internal canonical top-authority slug remains `super_admin`; business-facing label alias remains `admin`.

