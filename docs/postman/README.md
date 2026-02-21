# RAKEZ ERP – Postman Collections

Import these collections and the environment into Postman to call the Accounting and Credit APIs.

## Collections

| Collection | File | Description |
|------------|------|-------------|
| **Accounting (Complete)** | `collections/RAKEZ_ERP_Accounting_Complete.postman_collection.json` | All accounting endpoints: Dashboard, Notifications, Sold Units & Commissions, Deposits, Salaries, Legacy confirmations, Developers. |
| **Credit (Complete)** | `collections/RAKEZ_ERP_Credit_Complete.postman_collection.json` | All credit endpoints: Dashboard, Notifications, Bookings (confirmed/negotiation/waiting/sold/cancelled), Financing, Title Transfer, Claim Files, Payment Plan. |
| **AI Assistant v2** | `collections/RAKEZ_ERP_AI_V2.postman_collection.json` | Rakiz AI v2: Login, Chat (`POST /ai/v2/chat`), RAG Search (`POST /ai/v2/search`), Explain Access (`POST /ai/v2/explain-access`). Requires `use-ai-assistant`. |
| **Project Management** | `collections/RAKEZ_ERP_Project_Management.postman_collection.json` | Single PM collection: Auth, Dashboard, Teams, Contracts (list projects by segment, list contracts, status, project tracker stages/link, export PDF), Second Party, Units, Boards, Photography, Developers. Roles: project_management, admin. |
| **Marketing (Full)** | `collections/RAKEZ_ERP_Marketing_Full.postman_collection.json` | Marketing module: Dashboard, Projects, Developer/Employee Plans, Expected Sales, Tasks, Leads, Teams, Reports, Budget Distributions. Roles: marketing, admin. |
| **Sales (Full)** | `collections/RAKEZ_ERP_Sales_Full.postman_collection.json` | Sales and sales leaders: Dashboard, Projects, Reservations, Targets, Attendance, Team, Waiting list, Negotiations, Payment plan, Analytics, Commissions, Deposits; Admin project assignments. Roles: sales, sales_leader, admin. Some folders require sales_leader or admin (Team, Admin project assignments) or specific permissions (negotiations, payment plan, commissions). Uses `token` (set by Login). |
| **HR (Full)** | `collections/RAKEZ_ERP_HR_Full.postman_collection.json` | HR and admin: Dashboard, Teams, Marketer Performance, Users, Warnings, Employee Contracts, Reports, Teams (contracts/sales). Roles: hr, admin. Permissions: hr.dashboard.view, hr.teams.manage, hr.performance.view, hr.employees.manage, hr.warnings.manage, hr.contracts.manage, hr.reports.view. Uses `token` (set by Login). |

## Environment

- **Rakez ERP - Local**: `environments/Rakez-ERP-Local.postman_environment.json`  
  Set `base_url` (e.g. `http://localhost:8000/api`), `user_email`, `user_password`. After **Login**, `auth_token` is set automatically.

## How to use

1. **Import**  
   Postman → Import → choose the collection JSON(s) and the environment JSON.

2. **Select environment**  
   Top-right: select **Rakez ERP - Local** (or your copy).

3. **Login**  
   Run **00 - Auth → Login**. This sets `auth_token` in the environment.

4. **Set IDs**  
   For Accounting: set `reservation_id`, `commission_id`, `deposit_id`, `notification_id`, `distribution_id`, `employee_id` from list responses.  
   For Credit: set collection variables `booking_id`, `transfer_id`, `claim_file_id`, `notification_id`, `stage_number`, `installment_id` from list/detail responses (Collection → Variables).  
   For Project Management: optional `contract_id`, `team_id`, `unit_id`, `developer_number` (Create Team / Create Unit can set `team_id`, `unit_id`).  
   For Marketing: set `token` via 00 - Auth → Login (or use env); optional `project_id`, `contract_id`, `task_id`, `lead_id`, `plan_id`, `distribution_id`, `campaign_id`, `user_id`.  
   For Sales: set `token` via 00 - Auth → Login; optional `contract_id`, `contract_unit_id`, `unit_id`, `reservation_id`, `target_id`, `task_id`, `waiting_list_id`, `negotiation_id`, `commission_id`, `distribution_id`, `deposit_id`, `installment_id`, `leader_id`, `user_id`, `sales_reservation_id`.  
   For HR: set `token` via 00 - Auth → Login; optional `user_id`, `team_id`, `contract_id`, `warning_id`.

## Base URL

- No trailing slash: `http://localhost:8000/api`
- All request URLs in the collections use `{{base_url}}` + path.

## Auth

- Collections use **Bearer Token** with `{{auth_token}}`.
- Login request uses no auth and saves `access_token` into `auth_token`.

## Quick reference

- **Accounting**: ~28 requests (Auth, Dashboard, Notifications, Sold Units & Commissions, Deposits, Salaries, Legacy, Developers).
- **Credit**: ~35 requests (Auth, Dashboard, Notifications, Bookings, Financing, Title Transfer, Claim Files, Payment Plan).
- **AI v2**: 4 requests (Auth, Chat, RAG Search, Explain Access). For Vue/frontend integration see `docs/AI_V2_VUE_FRONTEND_PLAN.md`.
- **Project Management**: Single collection (Auth, Dashboard, Teams, Contracts including list projects by segment + project tracker + export PDF, Second Party, Units, Boards, Photography, Developers). Variables: `base_url`, `user_email`, `user_password`, `auth_token`; optional `contract_id`, `team_id`, `unit_id`, `stage_number`, `developer_number`.
- **Marketing (Full)**: Auth, Dashboard, Projects, Developer/Employee Plans, Expected Sales, Tasks, Leads, Teams, Reports, Budget Distributions. Variables: `base_url`, `token`, `user_email`, `user_password`; optional `project_id`, `contract_id`, `task_id`, `lead_id`, `plan_id`, `distribution_id`, `campaign_id`, `user_id`.
- **Sales (Full)**: Auth, Dashboard, Projects, Reservation context, Reservations, Targets, Attendance, Assignments, Team (Leader), Waiting list, Negotiations, Payment plan, Analytics, Commissions, Deposits, Admin project assignments. Variables: `base_url`, `token`, `user_email`, `user_password`; optional IDs: `contract_id`, `contract_unit_id`, `unit_id`, `reservation_id`, `target_id`, `task_id`, `waiting_list_id`, `negotiation_id`, `commission_id`, `distribution_id`, `deposit_id`, `installment_id`, `leader_id`, `user_id`, `sales_reservation_id`.
- **HR (Full)**: Auth, Dashboard, Teams, Marketer Performance, Users, Warnings, Contracts, Reports, Teams (contracts/sales). Variables: `base_url`, `token`, `user_email`, `user_password`; optional `user_id`, `team_id`, `contract_id`, `warning_id`.
