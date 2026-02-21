# API Routes & Permissions Mapping

## Complete mapping of all API routes with their required permissions

---

## 🔐 Authentication Routes (Public)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/api/login` | None | User login |

---

## 📋 Contract Routes

### User Contract Routes (Authenticated)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/contracts/index` | `contracts.view` | List user's contracts |
| POST | `/api/contracts/store` | `contracts.create` | Create new contract |
| GET | `/api/contracts/show/{id}` | `contracts.view` | View contract details |
| PUT | `/api/contracts/update/{id}` | `contracts.create` | Update contract |
| DELETE | `/api/contracts/{id}` | `contracts.delete` | Delete contract |
| POST | `/api/contracts/store/info/{id}` | `contracts.create` | Store contract info |

### Admin Contract Routes
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/contracts/admin-index` | `contracts.view_all` | View all contracts (Admin/PM) |
| PATCH | `/api/contracts/update-status/{id}` | `contracts.approve` | Update contract status (PM) |
| GET | `/api/admin/contracts/adminIndex` | `contracts.view_all` | Admin contract index |
| PATCH | `/api/admin/contracts/adminUpdateStatus/{id}` | `contracts.approve` | Admin update status |

---

## 🏢 Second Party Data Routes (Project Management)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/second-party-data/show/{id}` | `second_party.view` | View second party data |
| POST | `/api/second-party-data/store/{id}` | `second_party.edit` | Store second party data |
| PUT | `/api/second-party-data/update/{id}` | `second_party.edit` | Update second party data |
| GET | `/api/second-party-data/second-parties` | `second_party.view` | Get all second parties |
| GET | `/api/second-party-data/contracts-by-email` | `second_party.view` | Get contracts by email |

---

## 🏠 Contract Units Routes (Project Management)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/contracts/units/show/{contractId}` | `units.view` | View contract units |
| POST | `/api/contracts/units/upload-csv/{contractId}` | `units.csv_upload` | Upload units CSV |
| POST | `/api/contracts/units/store/{contractId}` | `units.edit` | Create unit |
| PUT | `/api/contracts/units/update/{unitId}` | `units.edit` | Update unit |
| DELETE | `/api/contracts/units/delete/{unitId}` | `units.edit` | Delete unit |

---

## 🎨 Department Routes (Project Management)

### Boards Department
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/boards-department/show/{contractId}` | `departments.boards.view` | View boards data |
| POST | `/api/boards-department/store/{contractId}` | `departments.boards.edit` | Store boards data |
| PUT | `/api/boards-department/update/{contractId}` | `departments.boards.edit` | Update boards data |

### Photography Department
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/photography-department/show/{contractId}` | `departments.photography.view` | View photography data |
| POST | `/api/photography-department/store/{contractId}` | `departments.photography.edit` | Store photography data |
| PUT | `/api/photography-department/update/{contractId}` | `departments.photography.edit` | Update photography data |

### Montage Department (Editor)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/editor/montage-department/show/{contractId}` | `departments.montage.view` | View montage data |
| POST | `/api/editor/montage-department/store/{contractId}` | `departments.montage.edit` | Store montage data |
| PUT | `/api/editor/montage-department/update/{contractId}` | `departments.montage.edit` | Update montage data |

---

## 📊 Dashboard Routes

### Project Management Dashboard
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/project_management/dashboard` | `dashboard.analytics.view` | View PM dashboard |
| GET | `/api/project_management/dashboard/units-statistics` | `dashboard.analytics.view` | View units statistics |

---

## 💼 Sales Department Routes

### Sales Dashboard & Projects
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/sales/dashboard` | `sales.dashboard.view` | View sales dashboard |
| GET | `/api/sales/projects` | `sales.projects.view` | List sales projects |
| GET | `/api/sales/projects/{contractId}` | `sales.projects.view` | View project details |
| GET | `/api/sales/projects/{contractId}/units` | `sales.projects.view` | View project units |

### Sales Reservations
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/sales/units/{unitId}/reservation-context` | `sales.reservations.create` | Get reservation context |
| POST | `/api/sales/reservations` | `sales.reservations.create` | Create reservation |
| GET | `/api/sales/reservations` | `sales.reservations.view` | List reservations |
| POST | `/api/sales/reservations/{id}/confirm` | `sales.reservations.confirm` | Confirm reservation |
| POST | `/api/sales/reservations/{id}/cancel` | `sales.reservations.cancel` | Cancel reservation |
| POST | `/api/sales/reservations/{id}/actions` | `sales.reservations.view` | Add reservation action |
| GET | `/api/sales/reservations/{id}/voucher` | `sales.reservations.view` | Download voucher |

### Sales Targets
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/sales/targets/my` | `sales.targets.view` | View my targets |
| PATCH | `/api/sales/targets/{id}` | `sales.targets.update` | Update target status |
| POST | `/api/sales/targets` | `sales.team.manage` | Create target (Leader) |

### Sales Attendance
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/sales/attendance/my` | `sales.attendance.view` | View my attendance |
| GET | `/api/sales/attendance/team` | `sales.team.manage` | View team attendance (Leader) |
| POST | `/api/sales/attendance/schedules` | `sales.team.manage` | Create schedule (Leader) |

### Sales Team Management (Leader Only)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/sales/team/projects` | `sales.team.manage` | View team projects |
| GET | `/api/sales/team/members` | `sales.team.manage` | View team members |
| PATCH | `/api/sales/projects/{contractId}/emergency-contacts` | `sales.team.manage` | Update emergency contacts |

### Marketing Tasks (Leader Only)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/sales/tasks/projects` | `sales.tasks.manage` | List task projects |
| GET | `/api/sales/tasks/projects/{contractId}` | `sales.tasks.manage` | View task project |
| POST | `/api/sales/marketing-tasks` | `sales.tasks.manage` | Create marketing task |
| PATCH | `/api/sales/marketing-tasks/{id}` | `sales.tasks.manage` | Update marketing task |

### Waiting List (NEW)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/sales/waiting-list` | `sales.waiting_list.create` | List waiting entries |
| GET | `/api/sales/waiting-list/unit/{unitId}` | `sales.waiting_list.create` | Get entries for unit |
| POST | `/api/sales/waiting-list` | `sales.waiting_list.create` | Create waiting entry |
| POST | `/api/sales/waiting-list/{id}/convert` | `sales.waiting_list.convert` | Convert to reservation (Leader) |
| DELETE | `/api/sales/waiting-list/{id}` | `sales.waiting_list.create` | Cancel waiting entry |

---

## 🎯 Marketing Department Routes

### Marketing Dashboard & Projects
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/marketing/dashboard` | `marketing.dashboard.view` | View marketing dashboard |
| GET | `/api/marketing/projects` | `marketing.projects.view` | List marketing projects |
| GET | `/api/marketing/projects/{contractId}` | `marketing.projects.view` | View project details |
| POST | `/api/marketing/projects/calculate-budget` | `marketing.budgets.manage` | Calculate budget |

### Marketing Plans
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/marketing/developer-plans/{contractId}` | `marketing.plans.create` | View developer plan |
| POST | `/api/marketing/developer-plans` | `marketing.plans.create` | Create developer plan |
| GET | `/api/marketing/employee-plans/project/{projectId}` | `marketing.plans.create` | List employee plans |
| GET | `/api/marketing/employee-plans/{planId}` | `marketing.plans.create` | View employee plan |
| POST | `/api/marketing/employee-plans` | `marketing.plans.create` | Create employee plan |
| POST | `/api/marketing/employee-plans/auto-generate` | `marketing.plans.create` | Auto-generate plans |

### Expected Sales
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/marketing/expected-sales/{projectId}` | `marketing.budgets.manage` | Calculate expected sales |
| PUT | `/api/marketing/settings/conversion-rate` | `marketing.budgets.manage` | Update conversion rate |

### Marketing Tasks
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/marketing/tasks` | `marketing.tasks.view` | List marketing tasks |
| POST | `/api/marketing/tasks` | `marketing.tasks.confirm` | Create task |
| PUT | `/api/marketing/tasks/{taskId}` | `marketing.tasks.confirm` | Update task |
| PATCH | `/api/marketing/tasks/{taskId}/status` | `marketing.tasks.confirm` | Update task status |

### Team Management
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/api/marketing/projects/{projectId}/team` | `marketing.projects.view` | Assign team |
| GET | `/api/marketing/projects/{projectId}/team` | `marketing.projects.view` | Get team |
| GET | `/api/marketing/projects/{projectId}/recommend-employee` | `marketing.projects.view` | Recommend employee |

### Leads
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/marketing/leads` | `marketing.projects.view` | List leads |
| POST | `/api/marketing/leads` | `marketing.projects.view` | Create lead |
| PUT | `/api/marketing/leads/{leadId}` | `marketing.projects.view` | Update lead |

### Marketing Reports
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/marketing/reports/project/{projectId}` | `marketing.reports.view` | Project performance |
| GET | `/api/marketing/reports/budget` | `marketing.reports.view` | Budget report |
| GET | `/api/marketing/reports/expected-bookings` | `marketing.reports.view` | Expected bookings |
| GET | `/api/marketing/reports/employee/{userId}` | `marketing.reports.view` | Employee performance |
| GET | `/api/marketing/reports/export/{planId}` | `marketing.reports.view` | Export plan |

### Marketing Settings
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/marketing/settings` | `marketing.budgets.manage` | View settings |
| PUT | `/api/marketing/settings/{key}` | `marketing.budgets.manage` | Update setting |

---

## 👥 HR & Employee Management Routes (Admin)

### Employee Management
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/api/admin/employees/add_employee` | `employees.manage` | Add employee |
| GET | `/api/admin/employees/list_employees` | `employees.manage` | List employees |
| GET | `/api/admin/employees/show_employee/{id}` | `employees.manage` | View employee |
| PUT | `/api/admin/employees/update_employee/{id}` | `employees.manage` | Update employee |
| DELETE | `/api/admin/employees/delete_employee/{id}` | `employees.manage` | Delete employee |
| PATCH | `/api/admin/employees/restore/{id}` | `employees.manage` | Restore employee |

---

## 🔔 Notification Routes

### User Notifications
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/user/notifications/private` | `notifications.view` | Get private notifications |
| GET | `/api/user/notifications/public` | `notifications.view` | Get public notifications |
| PATCH | `/api/user/notifications/mark-all-read` | `notifications.view` | Mark all as read |
| PATCH | `/api/user/notifications/{id}/read` | `notifications.view` | Mark as read |

### Admin Notifications
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/admin/notifications` | `notifications.view` | Get admin notifications |
| POST | `/api/admin/notifications/send-to-user` | `notifications.manage` | Send to user |
| POST | `/api/admin/notifications/send-public` | `notifications.manage` | Send public notification |
| GET | `/api/admin/notifications/user/{userId}` | `notifications.manage` | Get user notifications |
| GET | `/api/admin/notifications/public` | `notifications.manage` | Get all public |

---

## 🌟 Exclusive Project Routes (NEW)

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/api/exclusive-projects` | Auth | List exclusive project requests |
| GET | `/api/exclusive-projects/{id}` | Auth | View request details |
| POST | `/api/exclusive-projects` | `exclusive_projects.request` | Create request (All except HR) |
| POST | `/api/exclusive-projects/{id}/approve` | `exclusive_projects.approve` | Approve request (PM Manager) |
| POST | `/api/exclusive-projects/{id}/reject` | `exclusive_projects.approve` | Reject request (PM Manager) |
| PUT | `/api/exclusive-projects/{id}/contract` | `exclusive_projects.contract.complete` | Complete contract |
| GET | `/api/exclusive-projects/{id}/export` | `exclusive_projects.contract.export` | Export contract PDF |

---

## 🤖 AI Assistant Routes

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/api/ai/ask` | Auth | Ask AI question |
| POST | `/api/ai/chat` | Auth | Chat with AI |
| GET | `/api/ai/conversations` | Auth | List conversations |
| DELETE | `/api/ai/conversations/{sessionId}` | Auth | Delete conversation |
| GET | `/api/ai/sections` | Auth | Get available sections |

---

## 📈 Admin Sales Routes

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/api/admin/sales/project-assignments` | `sales.team.manage` | Assign project to team |

---

## Summary Statistics

- **Total Permissions:** 67
- **Total Roles:** 9
  - Admin
  - Project Management
  - Project Management Manager (via is_manager flag)
  - Sales Staff
  - Sales Leader
  - Editor
  - HR
  - Marketing
  - Developer

- **Total Protected Routes:** 150+
- **Public Routes:** 1 (login)

---

## Role Permission Matrix

| Permission Category | Admin | PM Staff | PM Manager | Sales | Sales Leader | Editor | HR | Marketing |
|---------------------|-------|----------|------------|-------|--------------|--------|-----|-----------|
| Contracts | ✅ All | View, Create | + Approve | View | View | View | ❌ | View |
| Units | ✅ All | Full | Full | View | View | ❌ | ❌ | ❌ |
| Departments | ✅ All | Boards, Photo | Full | ❌ | ❌ | Montage | ❌ | ❌ |
| Sales | ✅ All | ❌ | ❌ | Full | + Team Mgmt | ❌ | ❌ | ❌ |
| Waiting List | ✅ All | ❌ | ❌ | Create | + Convert | ❌ | ❌ | ❌ |
| Marketing | ✅ All | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | Full |
| Exclusive Projects | ✅ All | Request | + Approve | Request | Request | Request | ❌ | Request |
| HR/Employees | ✅ All | ❌ | ❌ | ❌ | ❌ | ❌ | Full | ❌ |
| Notifications | ✅ All | View | View | View | View | View | View | View |

---

## Notes

1. **Dynamic Permissions:** Project Management Managers get additional permissions dynamically through the `isProjectManagementManager()` check
2. **Middleware Stack:** Most routes use both role and permission middleware for double security
3. **HR Exclusion:** HR staff explicitly cannot access exclusive project features
4. **Sales Hierarchy:** Sales Leaders inherit all Sales Staff permissions plus additional team management capabilities
5. **Admin Override:** Admin role has ALL permissions across the entire system

---

**Last Updated:** 2026-01-27
**Version:** 1.0
