## Safe Write Tooling Design

This foundation is intentionally execution-disabled. It adds a unified AI-facing write orchestration layer without enabling any direct assistant write into business tables.

### Current operating rules

- `propose_write_action`: classify the requested mutation, normalize idempotency input, and build a dry-run proposal only.
- `preview_write_action`: return a preview envelope for a previously proposed payload.
- `confirm_write_action`: record confirmation intent, but never execute the underlying write.
- `reject_write_action`: record rejection and terminate the proposal.

### Required controls per action

Every future action definition must declare:

- strict input schema
- entity resolution contract
- ambiguity detection rules
- `dry_run_supported`
- `confirmation_required`
- idempotency key strategy
- audit log requirements
- rollback/failure behavior
- activation state

### Minimal skeleton now implemented

- `App\Services\AI\SafeWrites\SafeWriteActionRegistry`
- `App\Services\AI\SafeWrites\SafeWriteActionService`
- `App\Services\AI\SafeWrites\Contracts\SafeWriteActionHandler`
- `App\Services\AI\SafeWrites\Handlers\DraftBackedSafeWriteActionHandler`
- `App\Services\AI\SafeWrites\Handlers\MetadataOnlySafeWriteActionHandler`
- `App\Http\Controllers\AI\SafeWriteActionController`
- `/api/ai/write-actions/catalog`
- `/api/ai/write-actions/propose`
- `/api/ai/write-actions/preview`
- `/api/ai/write-actions/confirm`
- `/api/ai/write-actions/reject`

### Activation policy

- `safe_for_v1_draft_only`: allowed to produce draft payloads only. No direct execution.
- `safe_only_with_confirmation`: not enabled now. Future activation requires explicit confirmation plus stronger protections.
- `not_safe_for_assistant`: not eligible until missing locking, idempotency, or entity safety controls are added.
- `forbidden_entirely`: must not be enabled through the assistant channel.

## Reusable Services Inventory

Only reusable write surfaces discovered in real code are listed here. None should be called directly from AI-facing code before a protection wrapper exists.

| Surface | Current role | Reuse judgement | Main gap before any activation |
| --- | --- | --- | --- |
| `App\Services\Marketing\MarketingTaskService::createTask` | Creates marketing tasks and sends notifications | Reusable behind wrapper | Side effects require confirmation and failure isolation |
| `App\Services\Sales\MarketingTaskService::createTask` | Creates sales marketing tasks | Reusable behind wrapper | Team/project/entity confirmation required |
| `App\Services\Sales\SalesReservationService::logAction` | Appends reservation action log | Reusable behind wrapper | Must require explicit reservation id and idempotency |
| `App\Services\Sales\WaitingListService::createWaitingListEntry` | Creates waiting list entries | Reusable behind wrapper | Duplicate client detection and availability recheck missing |
| `App\Services\Sales\WaitingListService::convertToReservation` | Converts waiting entry into reservation | Not reusable for assistant now | High-risk state transition |
| `App\Services\Sales\SalesReservationService::createReservation` | Creates reservation and notifies departments | Not reusable for assistant now | Unit locking and duplicate prevention missing for AI path |
| `App\Services\Sales\SalesTargetService::createTarget` | Creates sales targets and syncs units | Reusable only after confirmation wrapper | Team-scope/entity matching must be strict |
| `App\Services\Team\TeamService::storeTeam` | Creates team | Reusable only after confirmation wrapper | Structural admin mutation |
| `App\Services\Team\TeamService::updateTeam` | Updates team metadata | Reusable only after confirmation wrapper | Structural admin mutation |
| `App\Services\Team\TeamService::assignSalesMemberToTeam` | Reassigns user to team | Not safe for assistant now | HR/org structure impact |
| `App\Services\Team\TeamService::removeMemberFromTeam` | Removes user from team | Not safe for assistant now | HR/org structure impact |
| `App\Services\ExclusiveProjectService::createRequest` | Creates exclusive project request | Reusable only after confirmation wrapper | Duplicate request detection and exact location resolution |
| `App\Services\ExclusiveProjectService::approveRequest` | Approves exclusive request | Forbidden entirely | Approval workflow |
| `App\Services\ExclusiveProjectService::rejectRequest` | Rejects exclusive request | Forbidden entirely | Approval workflow |
| `App\Services\ExclusiveProjectService::completeContract` | Creates linked contract artifacts | Forbidden entirely | Contract creation path |
| `App\Services\Sales\PaymentPlanService::createPlan` | Creates reservation payment plan | Forbidden entirely | Financial workflow |
| `App\Services\Sales\PaymentPlanService::updateInstallment` | Changes payment installment | Forbidden entirely | Financial workflow |
| `App\Services\Sales\PaymentPlanService::deleteInstallment` | Deletes installment | Forbidden entirely | Financial workflow |
| `App\Services\Sales\DepositService::createDeposit` | Creates deposit | Forbidden entirely | Financial workflow |
| `App\Services\Sales\DepositService::confirmReceipt` | Confirms deposit receipt | Forbidden entirely | Financial workflow |
| `App\Services\Sales\DepositService::refundDeposit` | Refunds deposit | Forbidden entirely | Financial workflow |
| `App\Services\Contract\ContractService` | Contract writes and status-linked workflows | Forbidden entirely | Contract/project mutation |
| `App\Services\Contract\SecondPartyDataService` | Writes contract counterparty info | Not safe for assistant | Contract-scoped mutation |
| `App\Services\Contract\ContractUnitService` | Writes project unit inventory | Forbidden entirely | Project inventory mutation |
| `App\Services\Contract\BoardsDepartmentService` | Department-specific contract writes | Not safe for assistant | Project execution mutation |
| `App\Services\Contract\MontageDepartmentService` | Department-specific contract writes and approvals | Forbidden entirely | Approval/state mutation |
| `App\Services\Contract\PhotographyDepartmentService` | Department-specific contract writes and approvals | Forbidden entirely | Approval/state mutation |
| `App\Services\HR\EmployeeContractService` | Creates HR contract records | Forbidden entirely | HR/legal mutation |
| `App\Services\registartion\register` | Employee CRUD | Forbidden entirely | Identity/permission mutation |

## Risk Matrix Per Write Action

Classification is based on the real API write surfaces discovered in `routes/api.php` plus the currently visible service layer.

### AI and knowledge surfaces

| Write action | Controller/method | Classification | Why |
| --- | --- | --- | --- |
| `POST /api/ai/drafts/prepare` | `AssistantDraftController::prepare` | Safe for v1 draft-only | Already draft-only, no DB business write |
| `POST /api/ai/documents` | `DocumentController::store` | Not safe for assistant | Knowledge-base mutation with ingestion side effects |
| `DELETE /api/ai/documents/{id}` | `DocumentController::destroy` | Not safe for assistant | Destructive knowledge deletion |
| `POST /api/ai/documents/{id}/reindex` | `DocumentController::reindex` | Not safe for assistant | Search/index side effects |

### Contract and project-management surfaces

| Write action | Controller/method | Classification | Why |
| --- | --- | --- | --- |
| `POST /api/contracts/store` | `ContractController::store` | Forbidden entirely | Contract creation |
| `PUT /api/contracts/update/{id}` | `ContractController::update` | Forbidden entirely | Contract mutation |
| `DELETE /api/contracts/{id}` | `ContractController::destroy` | Forbidden entirely | Destructive contract mutation |
| `PATCH /api/contracts/update-status/{id}` | `ContractController::projectManagementUpdateStatus` | Forbidden entirely | Status transition |
| `PATCH /api/admin/contracts/adminUpdateStatus/{id}` | `ContractController::adminUpdateStatus` | Forbidden entirely | Status transition |
| `POST /api/contracts/store/info/{id}` | `ContractInfoController::store` | Not safe for assistant | Contract-linked metadata write |
| `PUT /api/contracts/update/info/{id}` | `ContractInfoController::update` | Not safe for assistant | Contract-linked metadata write |
| `POST /api/contracts/second-party/store/{id}` | `SecondPartyDataController::store` | Not safe for assistant | Counterparty/legal data |
| `PUT /api/contracts/second-party/update/{id}` | `SecondPartyDataController::update` | Not safe for assistant | Counterparty/legal data |
| `POST /api/contracts/units/upload-csv/{contractId}` | `ContractUnitController::uploadCsvByContract` | Forbidden entirely | Bulk import |
| `POST /api/contracts/units/store/{contractId}` | `ContractUnitController::store` | Forbidden entirely | Inventory creation |
| `PUT /api/contracts/units/update/{unitId}` | `ContractUnitController::update` | Forbidden entirely | Inventory mutation |
| `DELETE /api/contracts/units/delete/{unitId}` | `ContractUnitController::destroy` | Forbidden entirely | Inventory deletion |
| `POST /api/contracts/boards/store/{contractId}` | `BoardsDepartmentController::store` | Not safe for assistant | Department workflow mutation |
| `PUT /api/contracts/boards/update/{contractId}` | `BoardsDepartmentController::update` | Not safe for assistant | Department workflow mutation |
| `POST /api/contracts/montage/store/{contractId}` | `MontageDepartmentController::store` | Not safe for assistant | Department workflow mutation |
| `PUT /api/contracts/montage/update/{contractId}` | `MontageDepartmentController::update` | Not safe for assistant | Department workflow mutation |
| `PATCH /api/contracts/montage/approve/{contractId}` | `MontageDepartmentController::approve` | Forbidden entirely | Approval |
| `POST /api/contracts/photography/store/{contractId}` | `PhotographyDepartmentController::store` | Not safe for assistant | Department workflow mutation |
| `PUT /api/contracts/photography/update/{contractId}` | `PhotographyDepartmentController::update` | Not safe for assistant | Department workflow mutation |
| `PATCH /api/contracts/photography/approve/{contractId}` | `PhotographyDepartmentController::approve` | Forbidden entirely | Approval |
| `POST /api/project_management/teams/store` | `TeamController::store` | Safe only with confirmation | Structural admin data, but controlled scope |
| `PUT /api/project_management/teams/update/{id}` | `TeamController::update` | Safe only with confirmation | Structural admin data |
| `DELETE /api/project_management/teams/delete/{id}` | `TeamController::destroy` | Not safe for assistant | Destructive organizational mutation |
| `POST /api/project_management/teams/members/{teamId}` | `TeamController::assignMember` | Not safe for assistant | Team membership reassignment |
| `DELETE /api/project_management/teams/members/{teamId}/{userId}` | `TeamController::removeMember` | Not safe for assistant | Team membership reassignment |
| `POST /api/project_management/contracts/add/{contractId}` | `ContractController::addTeamsToContract` | Not safe for assistant | Cross-entity project assignment |
| `POST /api/project_management/contracts/remove/{contractId}` | `ContractController::removeTeamsFromContract` | Not safe for assistant | Cross-entity project assignment |

### Sales and reservation surfaces

| Write action | Controller/method | Classification | Why |
| --- | --- | --- | --- |
| `POST /api/sales/reservations` | `SalesReservationController::store` | Not safe for assistant | High-value customer/project write |
| `POST /api/sales/reservations/{id}/confirm` | `SalesReservationController::confirm` | Forbidden entirely | Reservation status transition |
| `POST /api/sales/reservations/{id}/cancel` | `SalesReservationController::cancel` | Forbidden entirely | Reservation status transition |
| `POST /api/sales/reservations/{id}/actions` | `SalesReservationController::storeAction` | Safe for v1 draft-only | Low-risk append-only note/action pattern |
| `PATCH /api/sales/targets/{id}` | `SalesTargetController::update` | Not safe for assistant | Goal/status mutation |
| `PATCH /api/sales/team/members/{memberId}/rating` | `SalesTeamController::rateMember` | Not safe for assistant | Personnel evaluation |
| `POST /api/sales/team/members/{memberId}/remove` | `SalesTeamController::removeMember` | Not safe for assistant | Org structure mutation |
| `PATCH /api/sales/projects/{contractId}/emergency-contacts` | `SalesProjectController::updateEmergencyContacts` | Not safe for assistant | Sensitive project contact data |
| `POST /api/sales/targets` | `SalesTargetController::store` | Safe only with confirmation | Explicit entity selection possible, but still operationally sensitive |
| `POST /api/sales/attendance/schedules` | `SalesAttendanceController::store` | Not safe for assistant | Workforce schedule mutation |
| `POST /api/sales/attendance/project/{contractId}/bulk` | `SalesAttendanceController::bulkStore` | Forbidden entirely | Bulk action |
| `POST /api/sales/marketing-tasks` | `MarketingTaskController::store` | Safe for v1 draft-only | Scoped task creation, already supports form validation |
| `PATCH /api/sales/marketing-tasks/{id}` | `MarketingTaskController::update` | Not safe for assistant | Existing task mutation |
| `POST /api/sales/waiting-list` | `WaitingListController::store` | Safe only with confirmation | Single-record operational write with duplicate/availability risk |
| `POST /api/sales/waiting-list/{id}/convert` | `WaitingListController::convert` | Forbidden entirely | Converts into reservation |
| `DELETE /api/sales/waiting-list/{id}` | `WaitingListController::cancel` | Not safe for assistant | State change/cancellation |
| `POST /api/sales/project-assignments` | `SalesProjectController::assignProject` | Not safe for assistant | Cross-team assignment |

### Registration, HR, and personnel surfaces

| Write action | Controller/method | Classification | Why |
| --- | --- | --- | --- |
| `POST /api/register/add_employee` | `RegisterController::add_employee` | Forbidden entirely | Identity/user creation |
| `PUT /api/register/update_employee/{id}` | `RegisterController::update_employee` | Forbidden entirely | Identity/user mutation |
| `DELETE /api/register/delete_employee/{id}` | `RegisterController::delete_employee` | Forbidden entirely | Identity/user deletion |
| `PATCH /api/register/restore/{id}` | `RegisterController::restore_employee` | Forbidden entirely | Identity/user restoration |
| `POST /api/hr/users` | `HrUserController::store` | Forbidden entirely | HR identity creation |
| `PUT /api/hr/users/{id}` | `HrUserController::update` | Forbidden entirely | HR identity mutation |
| `DELETE /api/hr/users/{id}` | `HrUserController::destroy` | Forbidden entirely | HR identity deletion |
| `PATCH /api/hr/users/{id}/status` | `HrUserController::toggleStatus` | Forbidden entirely | Employment status transition |
| `POST /api/hr/users/{id}/contracts` | `EmployeeContractController::store` | Forbidden entirely | HR contract creation |
| `POST /api/hr/users/{id}/files` | `HrUserController::uploadFiles` | Not safe for assistant | Sensitive file upload |
| `POST /api/hr/users/{id}/warnings` | `EmployeeWarningController::store` | Forbidden entirely | Disciplinary record creation |
| `POST /api/hr/teams` | `HrTeamController::store` | Safe only with confirmation | Structural admin data |
| `PUT /api/hr/teams/{id}` | `HrTeamController::update` | Safe only with confirmation | Structural admin data |
| `DELETE /api/hr/teams/{id}` | `HrTeamController::destroy` | Not safe for assistant | Destructive org mutation |
| `POST /api/hr/teams/{id}/members` | `HrTeamController::assignMember` | Not safe for assistant | HR assignment mutation |
| `DELETE /api/hr/teams/{id}/members/{userId}` | `HrTeamController::removeMember` | Not safe for assistant | HR assignment mutation |
| `POST /api/employees/{id}/reviews` | `ManagerEmployeeController::storeReview` | Not safe for assistant | Performance review |
| `PUT /api/employees/{employeeId}/reviews/{reviewId}` | `ManagerEmployeeController::updateReview` | Not safe for assistant | Performance review |
| `DELETE /api/employees/{employeeId}/reviews/{reviewId}` | `ManagerEmployeeController::deleteReview` | Not safe for assistant | Performance review |

### Notification and settings surfaces

| Write action | Controller/method | Classification | Why |
| --- | --- | --- | --- |
| `PATCH /api/notifications/mark-all-read` | `NotificationController::userMarkAllAsRead` | Safe only with confirmation | User-local, low-impact mutation |
| `PATCH /api/notifications/{id}/read` | `NotificationController::userMarkAsRead` | Safe only with confirmation | User-local, low-impact mutation |
| `POST /api/admin/notifications/send-to-user` | `NotificationController::sendToUser` | Not safe for assistant | Outbound messaging side effect |
| `POST /api/admin/notifications/send-public` | `NotificationController::sendPublic` | Not safe for assistant | Broad outbound messaging |
| `PUT /api/marketing/settings/{key}` | `MarketingSettingsController::update` | Not safe for assistant | Global settings mutation |
| `PUT /api/marketing/settings/conversion-rate` | `ExpectedSalesController::updateConversionRate` | Not safe for assistant | Global settings mutation |

### Geographic admin surfaces

| Write action | Controller/method | Classification | Why |
| --- | --- | --- | --- |
| `POST /api/cities` | `CityController::store` | Safe only with confirmation | Small scoped admin master data |
| `PUT/PATCH /api/cities/{id}` | `CityController::update` | Safe only with confirmation | Small scoped admin master data |
| `DELETE /api/cities/{id}` | `CityController::destroy` | Not safe for assistant | Destructive master-data mutation |
| `POST /api/districts` | `DistrictController::store` | Safe only with confirmation | Small scoped admin master data |
| `PUT/PATCH /api/districts/{id}` | `DistrictController::update` | Safe only with confirmation | Small scoped admin master data |
| `DELETE /api/districts/{id}` | `DistrictController::destroy` | Not safe for assistant | Destructive master-data mutation |

### Exclusive projects and marketing module surfaces

| Write action | Controller/method | Classification | Why |
| --- | --- | --- | --- |
| `POST /api/exclusive-projects` | `ExclusiveProjectController::store` | Safe only with confirmation | Internal request creation with exact fields possible |
| `POST /api/exclusive-projects/{id}/approve` | `ExclusiveProjectController::approve` | Forbidden entirely | Approval |
| `POST /api/exclusive-projects/{id}/reject` | `ExclusiveProjectController::reject` | Forbidden entirely | Approval |
| `PUT /api/exclusive-projects/{id}/contract` | `ExclusiveProjectController::completeContract` | Forbidden entirely | Contract completion |
| `POST /api/marketing/projects/calculate-budget` | `MarketingProjectController::calculateBudget` | Not safe for assistant | Operational planning side effect path |
| `POST /api/marketing/developer-plans/calculate-budget` | `DeveloperMarketingPlanController::calculateBudget` | Not safe for assistant | Planning mutation path |
| `POST /api/marketing/developer-plans` | `DeveloperMarketingPlanController::store` | Not safe for assistant | Planning mutation |
| `POST /api/marketing/employee-plans` | `EmployeeMarketingPlanController::store` | Not safe for assistant | Planning mutation |
| `POST /api/marketing/employee-plans/auto-generate` | `EmployeeMarketingPlanController::autoGenerate` | Forbidden entirely | Bulk/derived generation |
| `POST /api/marketing/tasks` | `MarketingModuleTaskController::store` | Safe for v1 draft-only | Low-risk task creation |
| `PUT /api/marketing/tasks/{taskId}` | `MarketingModuleTaskController::update` | Not safe for assistant | Existing task mutation |
| `PATCH /api/marketing/tasks/{taskId}/status` | `MarketingModuleTaskController::updateStatus` | Not safe for assistant | Workflow state mutation |
| `POST /api/marketing/projects/{projectId}/team` | `TeamManagementController::assignTeam` | Not safe for assistant | Cross-team assignment |
| `POST /api/marketing/leads` | `LeadController::store` | Safe for v1 draft-only | Draft candidate only; activation blocked until lead surface hardening |
| `PUT /api/marketing/leads/{leadId}` | `LeadController::update` | Not safe for assistant | Existing customer/lead mutation |

### Miscellaneous user-owned task surfaces

| Write action | Controller/method | Classification | Why |
| --- | --- | --- | --- |
| `POST /api/tasks` | `MyTasksController::store` | Safe for v1 draft-only | Low-risk personal/task workflow |
| `PATCH /api/my-tasks/{id}/status` | `MyTasksController::updateStatus` | Not safe for assistant | Existing task state mutation |

## Minimal Implementation Skeleton Without Unsafe Activation

### What is intentionally enabled

- Cataloging allowed action definitions for the current user.
- Producing dry-run proposals for draft-backed actions.
- Previewing proposals.
- Recording confirm/reject attempts in audit.

### What is intentionally disabled

- Any direct execution of a business write from the assistant channel.
- Any financial write.
- Any approval or rejection workflow.
- Any status transition on contracts, reservations, deposits, or approvals.
- Any bulk mutation.

### Migration path later

Future execution enablement must happen one action at a time and only after all of the following exist for that action:

1. exact input schema tied to a real validator
2. exact entity resolution with ambiguity refusal
3. explicit user confirmation UX
4. idempotency persistence
5. before/after audit payloads
6. transaction boundaries
7. rollback/failure policy
8. focused regression tests
