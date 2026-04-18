# Filament Writable Action Duplication Matrix

| File | Writable Entry Point | Backend Service Path | Thin-Console Check |
|---|---|---|---|
| app\Filament\Admin\Resources\AccountingClaimFiles\Pages\ListAccountingClaimFiles.php | generateBulk | ClaimFileService::class | Shared service/action used |
| app\Filament\Admin\Resources\AccountingClaimFiles\Pages\ListAccountingClaimFiles.php | generateCombined | ClaimFileService::class | Shared service/action used |
| app\Filament\Admin\Resources\AccountingDeposits\AccountingDepositResource.php | confirmDeposit | AccountingDepositService::class | Shared service/action used |
| app\Filament\Admin\Resources\AccountingDeposits\AccountingDepositResource.php | processRefund | AccountingDepositService::class | Shared service/action used |
| app\Filament\Admin\Resources\AccountingNotifications\AccountingNotificationResource.php | markRead | AccountingNotificationService::class | Shared service/action used |
| app\Filament\Admin\Resources\AccountingNotifications\Pages\ListAccountingNotifications.php | markAllRead | AccountingNotificationService::class | Shared service/action used |
| app\Filament\Admin\Resources\AdminNotifications\AdminNotificationResource.php | markRead | NotificationAdminService::class | Shared service/action used |
| app\Filament\Admin\Resources\AdminNotifications\Pages\ListAdminNotifications.php | markAllRead | NotificationAdminService::class | Shared service/action used |
| app\Filament\Admin\Resources\AdminNotifications\Pages\ListAdminNotifications.php | sendAdminNotification | NotificationAdminService::class | Shared service/action used |
| app\Filament\Admin\Resources\AssistantKnowledgeEntries\AssistantKnowledgeEntryResource.php | deleteEntry | AssistantKnowledgeEntryService::class | Shared service/action used |
| app\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages\CreateAssistantKnowledgeEntry.php | handleRecordCreation | AssistantKnowledgeEntryService::class | Shared service/action used |
| app\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages\EditAssistantKnowledgeEntry.php | deleteEntry | AssistantKnowledgeEntryService::class | Shared service/action used |
| app\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages\EditAssistantKnowledgeEntry.php | handleRecordUpdate | AssistantKnowledgeEntryService::class | Shared service/action used |
| app\Filament\Admin\Resources\ClaimFiles\ClaimFileResource.php | generatePdf | ClaimFileService::class | Shared service/action used |
| app\Filament\Admin\Resources\ClaimFiles\Pages\ListClaimFiles.php | generateBulk | ClaimFileService::class | Shared service/action used |
| app\Filament\Admin\Resources\ClaimFiles\Pages\ListClaimFiles.php | generateCombined | ClaimFileService::class | Shared service/action used |
| app\Filament\Admin\Resources\CommissionDistributions\CommissionDistributionResource.php | approveDistribution | AccountingCommissionService::class | Shared service/action used |
| app\Filament\Admin\Resources\CommissionDistributions\CommissionDistributionResource.php | markPaid | AccountingCommissionService::class | Shared service/action used |
| app\Filament\Admin\Resources\CommissionDistributions\CommissionDistributionResource.php | rejectDistribution | AccountingCommissionService::class | Shared service/action used |
| app\Filament\Admin\Resources\Contracts\ContractResource.php | approveContract | ContractService::class | Shared service/action used |
| app\Filament\Admin\Resources\Contracts\ContractResource.php | markReadyForMarketing | ContractService::class | Shared service/action used |
| app\Filament\Admin\Resources\Contracts\ContractResource.php | rejectContract | ContractService::class | Shared service/action used |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | advanceFinancing | CreditFinancingService::class | Shared service/action used |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | cancelBooking | SalesReservationService::class | Shared service/action used |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | editClient | SalesReservationService::class | Shared service/action used |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | generateClaimFile | ClaimFileService::class | Shared service/action used |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | generateClaimPdf | ClaimFileService::class | Shared service/action used |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | logClientContact | SalesReservationService::class | Shared service/action used |
| app\Filament\Admin\Resources\CreditBookings\CreditBookingResource.php | rejectFinancing | CreditFinancingService::class | Shared service/action used |
| app\Filament\Admin\Resources\CreditNotifications\CreditNotificationResource.php | markRead | CreditNotificationService::class | Shared service/action used |
| app\Filament\Admin\Resources\CreditNotifications\Pages\ListCreditNotifications.php | markAllRead | CreditNotificationService::class | Shared service/action used |
| app\Filament\Admin\Resources\DirectPermissions\Pages\EditDirectPermissions.php | handleRecordUpdate | DirectPermissionGovernanceService::class | Shared service/action used |
| app\Filament\Admin\Resources\EmployeeContracts\EmployeeContractResource.php | activateContract | EmployeeContractService::class | Shared service/action used |
| app\Filament\Admin\Resources\EmployeeContracts\EmployeeContractResource.php | editContract | EmployeeContractService::class | Shared service/action used |
| app\Filament\Admin\Resources\EmployeeContracts\EmployeeContractResource.php | generatePdf | EmployeeContractService::class | Shared service/action used |
| app\Filament\Admin\Resources\EmployeeContracts\EmployeeContractResource.php | terminateContract | EmployeeContractService::class | Shared service/action used |
| app\Filament\Admin\Resources\EmployeeContracts\Pages\ListEmployeeContracts.php | createContract | EmployeeContractService::class | Shared service/action used |
| app\Filament\Admin\Resources\EmployeeWarnings\EmployeeWarningResource.php | deleteWarning | EmployeeWarningService::class | Shared service/action used |
| app\Filament\Admin\Resources\EmployeeWarnings\Pages\ListEmployeeWarnings.php | issueWarning | EmployeeWarningService::class | Shared service/action used |
| app\Filament\Admin\Resources\ExclusiveProjectRequests\ExclusiveProjectRequestResource.php | approveRequest | ExclusiveProjectService::class | Shared service/action used |
| app\Filament\Admin\Resources\ExclusiveProjectRequests\ExclusiveProjectRequestResource.php | completeContract | ExclusiveProjectService::class | Shared service/action used |
| app\Filament\Admin\Resources\ExclusiveProjectRequests\ExclusiveProjectRequestResource.php | exportContract | ExclusiveProjectService::class | Shared service/action used |
| app\Filament\Admin\Resources\ExclusiveProjectRequests\ExclusiveProjectRequestResource.php | rejectRequest | ExclusiveProjectService::class | Shared service/action used |
| app\Filament\Admin\Resources\GovernanceTemporaryPermissions\GovernanceTemporaryPermissionResource.php | revoke | GovernanceTemporaryPermissionService::class | Shared service/action used |
| app\Filament\Admin\Resources\GovernanceTemporaryPermissions\Pages\CreateGovernanceTemporaryPermission.php | handleRecordCreation | GovernanceTemporaryPermissionService::class | Shared service/action used |
| app\Filament\Admin\Resources\HrTeams\HrTeamResource.php | deleteTeam | TeamService::class | Shared service/action used |
| app\Filament\Admin\Resources\HrTeams\Pages\CreateHrTeam.php | handleRecordCreation | TeamService::class | Shared service/action used |
| app\Filament\Admin\Resources\HrTeams\Pages\EditHrTeam.php | deleteTeam | TeamService::class | Shared service/action used |
| app\Filament\Admin\Resources\HrTeams\Pages\EditHrTeam.php | handleRecordUpdate | TeamService::class | Shared service/action used |
| app\Filament\Admin\Resources\InventoryUnits\InventoryUnitResource.php | deleteUnit | ContractUnitService::class | Shared service/action used |
| app\Filament\Admin\Resources\InventoryUnits\Pages\EditInventoryUnit.php | deleteUnit | ContractUnitService::class | Shared service/action used |
| app\Filament\Admin\Resources\InventoryUnits\Pages\EditInventoryUnit.php | handleRecordUpdate | ContractUnitService::class | Shared service/action used |
| app\Filament\Admin\Resources\MarketingTasks\MarketingTaskResource.php | deleteTask | MarketingTaskService::class | Shared service/action used |
| app\Filament\Admin\Resources\MarketingTasks\MarketingTaskResource.php | markCompleted | MarketingTaskService::class | Shared service/action used |
| app\Filament\Admin\Resources\MarketingTasks\Pages\CreateMarketingTask.php | handleRecordCreation | MarketingTaskService::class | Shared service/action used |
| app\Filament\Admin\Resources\MarketingTasks\Pages\EditMarketingTask.php | deleteTask | MarketingTaskService::class | Shared service/action used |
| app\Filament\Admin\Resources\MarketingTasks\Pages\EditMarketingTask.php | handleRecordUpdate | MarketingTaskService::class | Shared service/action used |
| app\Filament\Admin\Resources\Roles\Pages\EditRole.php | handleRecordUpdate | RoleGovernanceService::class | Shared service/action used |
| app\Filament\Admin\Resources\SalaryDistributions\SalaryDistributionResource.php | approveSalary | AccountingSalaryService::class | Shared service/action used |
| app\Filament\Admin\Resources\SalaryDistributions\SalaryDistributionResource.php | markSalaryPaid | AccountingSalaryService::class | Shared service/action used |
| app\Filament\Admin\Resources\SalesAttendanceSchedules\SalesAttendanceScheduleResource.php | deleteSchedule | SalesAttendanceService::class | Shared service/action used |
| app\Filament\Admin\Resources\SalesReservations\SalesReservationResource.php | cancelReservation | SalesReservationService::class | Shared service/action used |
| app\Filament\Admin\Resources\SalesReservations\SalesReservationResource.php | confirmReservation | SalesReservationService::class | Shared service/action used |
| app\Filament\Admin\Resources\SalesTargets\SalesTargetResource.php | updateTargetStatus | SalesTargetService::class | Shared service/action used |
| app\Filament\Admin\Resources\TitleTransfers\TitleTransferResource.php | completeTransfer | TitleTransferService::class | Shared service/action used |
| app\Filament\Admin\Resources\TitleTransfers\TitleTransferResource.php | scheduleTransfer | TitleTransferService::class | Shared service/action used |
| app\Filament\Admin\Resources\TitleTransfers\TitleTransferResource.php | unscheduleTransfer | TitleTransferService::class | Shared service/action used |
| app\Filament\Admin\Resources\UserNotifications\Pages\ListUserNotifications.php | markAllRead | NotificationAdminService::class | Shared service/action used |
| app\Filament\Admin\Resources\UserNotifications\Pages\ListUserNotifications.php | sendPublicNotification | NotificationAdminService::class | Shared service/action used |
| app\Filament\Admin\Resources\UserNotifications\Pages\ListUserNotifications.php | sendUserNotification | NotificationAdminService::class | Shared service/action used |
| app\Filament\Admin\Resources\UserNotifications\UserNotificationResource.php | markRead | NotificationAdminService::class | Shared service/action used |
| app\Filament\Admin\Resources\Users\Pages\CreateUser.php | handleRecordCreation | UserGovernanceService::class | Shared service/action used |
| app\Filament\Admin\Resources\Users\Pages\EditUser.php | deleteUser | UserGovernanceService::class | Shared service/action used |
| app\Filament\Admin\Resources\Users\Pages\EditUser.php | handleRecordUpdate | UserGovernanceService::class | Shared service/action used |
| app\Filament\Admin\Resources\Users\UserResource.php | deleteUser | UserGovernanceService::class | Shared service/action used |
| app\Filament\Admin\Resources\Users\UserResource.php | restoreUser | UserGovernanceService::class | Shared service/action used |
| app\Filament\Admin\Resources\WorkflowTasks\Pages\ListWorkflowTasks.php | createTask | WorkflowTaskAdminService::class | Shared service/action used |
| app\Filament\Admin\Resources\WorkflowTasks\WorkflowTaskResource.php | markCompleted | WorkflowTaskAdminService::class | Shared service/action used |
| app\Filament\Admin\Resources\WorkflowTasks\WorkflowTaskResource.php | markCouldNotComplete | WorkflowTaskAdminService::class | Shared service/action used |
| app\Filament\Admin\Resources\WorkflowTasks\WorkflowTaskResource.php | markInProgress | WorkflowTaskAdminService::class | Shared service/action used |

Total writable entry points scanned: 80
Shared backend usage detected: 80
Potential duplicates to review: 0
