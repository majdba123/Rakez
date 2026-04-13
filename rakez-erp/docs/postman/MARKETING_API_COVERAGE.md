# Marketing API — endpoint coverage report

**Source of truth:** `rakez-erp/routes/api.php` (marketing group, `ads` group, sales marketing-task routes).  
**Collection:** `collections/Rakez-Marketing-API.postman_collection.json`  
**Live verification:** Automated probe: `https://rakez.com.sa/api/*` returned **404** (nginx); **no JSON live verification** completed. All rows below are **code-verified** unless noted.

| Folder | Request name | Method | Route (relative to `baseUrl`) | Live verified? | Safe on prod? | Needs auth? | Notes |
|--------|----------------|--------|----------------------------------|----------------|---------------|-------------|-------|
| Auth | Login (Sanctum token) | POST | `/login` | No (404 on apex `/api`) | Yes (invalid creds) | No | Real success needs valid user |
| Auth | Logout | POST | `/logout` | No | Yes | Yes | Revokes tokens |
| Marketing — Dashboard | Dashboard KPIs | GET | `/marketing/dashboard` | No | Read | Yes | `permission:marketing.dashboard.view` |
| Marketing — Projects & budget | List marketing projects | GET | `/marketing/projects` | No | Read | Yes | `marketing.projects.view` |
| Marketing — Projects & budget | Show project by contract | GET | `/marketing/projects/{contractId}` | No | Read | Yes | |
| Marketing — Projects & budget | Calculate project budget (preview) | POST | `/marketing/projects/calculate-budget` | No | Mutating | Yes | `marketing.budgets.manage`; placeholder body |
| Marketing — Developer plans | Show developer plan | GET | `/marketing/developer-plans/{contractId}` | No | Read | Yes | |
| Marketing — Developer plans | Download developer plan PDF | GET | `/marketing/developer-plans/{contractId}/pdf` | No | Read | Yes | Binary |
| Marketing — Developer plans | Developer plan PDF data (JSON) | GET | `/marketing/reports/developer-plan/{contractId}/pdf-data` | No | Read | Yes | |
| Marketing — Developer plans | Calculate developer plan budget | POST | `/marketing/developer-plans/calculate-budget` | No | Mutating | Yes | Placeholder body |
| Marketing — Developer plans | Store developer plan | POST | `/marketing/developer-plans` | No | Mutating | Yes | Placeholder body |
| Marketing — Users | List users (dropdown) | GET | `/marketing/users` | No | Read | Yes | Same as HR users index |
| Marketing — Employee plans | List employee plans | GET | `/marketing/employee-plans` | No | Read | Yes | Optional `project_id` query |
| Marketing — Employee plans | List employee plans for project | GET | `/marketing/employee-plans/project/{projectId}` | No | Read | Yes | |
| Marketing — Employee plans | Employee plans PDF data | GET | `/marketing/employee-plans/pdf-data` | No | Read | Yes | See controller for query |
| Marketing — Employee plans | Show employee plan | GET | `/marketing/employee-plans/{planId}` | No | Read | Yes | |
| Marketing — Employee plans | Create employee plan | POST | `/marketing/employee-plans` | No | Mutating | Yes | Placeholder body |
| Marketing — Employee plans | Auto-generate employee plans | POST | `/marketing/employee-plans/auto-generate` | No | Mutating | Yes | Placeholder body |
| Marketing — Expected sales | Calculate / upsert expected sales | GET | `/marketing/expected-sales/{projectId}` | No | **Writes** expected bookings | Yes | `CalculateExpectedSalesRequest` query params |
| Marketing — Expected sales | Update global conversion rate | PUT | `/marketing/settings/conversion-rate` | No | Mutating | Yes | Body `{ "value" }` |
| Marketing — Tasks | List my marketing tasks | GET | `/marketing/tasks` | No | Read | Yes | Query `date`, `status` |
| Marketing — Tasks | Create marketing task | POST | `/marketing/tasks` | No | Mutating | Yes | |
| Marketing — Tasks | Update marketing task | PUT | `/marketing/tasks/{taskId}` | No | Mutating | Yes | |
| Marketing — Tasks | Update task status | PATCH | `/marketing/tasks/{taskId}/status` | No | Mutating | Yes | |
| Marketing — Project team | Assign team to project | POST | `/marketing/projects/{projectId}/team` | No | Mutating | Yes | |
| Marketing — Project team | Get project team | GET | `/marketing/projects/{projectId}/team` | No | Read | Yes | |
| Marketing — Project team | Recommend employee | GET | `/marketing/projects/{projectId}/recommend-employee` | No | Read | Yes | |
| Marketing — Employees | List marketing employees | GET | `/marketing/employees` | No | Read | Yes | |
| Marketing — Employees | Show marketing employee | GET | `/marketing/employees/{id}` | No | Read | Yes | |
| Marketing — Leads | List leads | GET | `/marketing/leads` | No | Read | Yes | Paginated |
| Marketing — Leads | Create lead | POST | `/marketing/leads` | No | Mutating | Yes | `project_id` = `contracts.id` |
| Marketing — Leads | Update lead | PUT | `/marketing/leads/{leadId}` | No | Mutating | Yes | |
| Marketing — Reports | Project performance report | GET | `/marketing/reports/project/{projectId}` | No | Read | Yes | |
| Marketing — Reports | Budget report | GET | `/marketing/reports/budget` | No | Read | Yes | |
| Marketing — Reports | Expected bookings report | GET | `/marketing/reports/expected-bookings` | No | Read | Yes | |
| Marketing — Reports | Employee performance | GET | `/marketing/reports/employee/{userId}` | No | Read | Yes | |
| Marketing — Reports | Export employee plan | GET | `/marketing/reports/export/{planId}` | No | Read | Yes | Query `format`: pdf, excel, csv |
| Marketing — Reports | Export distribution PDF by project | GET | `/marketing/reports/distribution/project/{projectId}` | No | Read | Yes | PDF |
| Marketing — Reports | Export distribution PDF by plan | GET | `/marketing/reports/distribution/{planId}` | No | Read | Yes | Employee plan id |
| Marketing — Settings | List marketing settings | GET | `/marketing/settings` | No | Read | Yes | |
| Marketing — Settings | Update setting by key | PUT | `/marketing/settings/{key}` | No | Mutating | Yes | |
| Ads | Ads — list accounts | GET | `/ads/accounts` | No | Read | Yes | `role:admin|marketing`, ads permissions |
| Ads | Ads — campaigns | GET | `/ads/campaigns` | No | Read | Yes | |
| Ads | Ads — insights | GET | `/ads/insights` | No | Read | Yes | |
| Ads | Ads — leads | GET | `/ads/leads` | No | Read | Yes | |
| Ads | Ads — leads export | GET | `/ads/leads/export` | No | Read | Yes | |
| Ads | Ads — export snapshot | POST | `/ads/leads/export-snap` | No | Mutating | Yes | Not executed |
| Ads | Ads — trigger sync | POST | `/ads/sync` | No | Mutating | Yes | Not executed |
| Ads | Ads — store outcome | POST | `/ads/outcomes` | No | Mutating | Yes | Not executed |
| Ads | Ads — outcome status | GET | `/ads/outcomes/status` | No | Read | Yes | |
| Sales API — Marketing tasks | List projects with marketing tasks | GET | `/sales/tasks/projects` | No | Read | Yes | Sales leader + `sales.tasks.manage` |
| Sales API — Marketing tasks | Marketing tasks for contract | GET | `/sales/tasks/projects/{contractId}` | No | Read | Yes | |
| Sales API — Marketing tasks | Create marketing task (sales) | POST | `/sales/marketing-tasks` | No | Mutating | Yes | Not executed |
| Sales API — Marketing tasks | Patch marketing task (sales) | PATCH | `/sales/marketing-tasks/{taskId}` | No | Mutating | Yes | Not executed |

## Intentionally not executed on production

All **POST/PUT/PATCH** and **mutating GET** examples use placeholders. **Ads** sync/outcome/snap POSTs were not called. **No production data** was created or updated for this deliverable.

## Verdict

| Status | Meaning |
|--------|---------|
| **Partially production-usable** | Routes and examples match **current** Laravel; **auth/permissions** and correct **`baseUrl`** are required. **Live** verification was **blocked** by nginx 404 on `https://rakez.com.sa/api` in the probe — confirm API host (e.g. `api.rakez.com.sa`) before relying on the default `baseUrl`. |
