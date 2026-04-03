# Admin & System-Wide Frontend Design Specification

**Audience:** UI/UX designers, product designers, and frontend architects building the Rakez ERP web application.

**Language:** This document is written in English as requested.

**Scope:** Describes **what the backend already exposes** for users with the **`admin`** role (`users.type === 'admin'`), including routes that are **admin-only** and routes where **admin is explicitly included** alongside other department roles. It is **not** a product roadmap: if a screen is listed here, the API contract exists in the current Laravel codebase (`routes/api.php`, middleware, and permission config).

**Source of truth (codebase):**

- `routes/api.php` — HTTP surface
- `bootstrap/app.php` — middleware aliases (`admin`, `hr`, `inventory`, etc.)
- `config/user_types.php` — user types and middleware allowances
- `config/ai_capabilities.php` — permission definitions; `RolesAndPermissionsSeeder` assigns **all** defined permissions to `admin`
- `app/Providers/AppServiceProvider.php` — `Gate::before` grants **admin** a full authorization bypass at the Laravel Gate level (admin effectively passes `can()` checks)

Design implication: the **frontend should still mirror permission keys** where you want to hide UI for future non-admin super-users or custom roles, but for a pure `admin` user you can treat most capability flags as **always on** unless you deliberately scope the admin UI.

---

## 1. Design goals for the admin experience

1. **Single operational cockpit** — Admins need to jump between HR, sales operations, finance/credit, marketing, inventory, contracts/project management, and AI tooling without context loss. Prefer a **persistent shell** (sidebar + top bar) with department-based sections.
2. **Arabic-first UX** — Many validation and error messages returned by Form Requests are **Arabic**. Design forms, toasts, and inline errors to support RTL layout and mixed Arabic/English content (e.g. developer names, codes).
3. **High-stakes actions** — Contract approval, deposit confirmation, commission approval, booking cancellation, and notification broadcasts need **confirmations**, **audit-friendly summaries**, and clear **irreversibility** cues where applicable.
4. **Density with drill-down** — Lists are often paginated (`per_page`, filters). Use **list → detail → action drawer/page** patterns consistently.
5. **Real-time where defined** — Broadcasting authorizes `admin-notifications` for `user.type === 'admin'`. Plan an **admin notification stream** (bell + feed) subscribed to that channel, in addition to REST `GET /api/admin/notifications`.

---

## 2. Authentication and session

| Concern | Backend fact | UX note |
|--------|---------------|--------|
| Login | `POST /api/login` | Support remember-device only if you add it client-side; API is token-based (Sanctum). |
| Current user | `GET /api/user` (inside `auth:sanctum`) | Drive avatar, name, type, and **role/permission display** for debugging or support mode. |
| Logout | `POST /api/logout` | Clear token and unsubscribe WebSocket channels. |
| CSRF (SPA) | `GET /api/csrf-token` | For cookie/session hybrids; many SPAs use Bearer tokens only. |

**Admin gate:** All admin-prefixed routes use `auth:sanctum` + `role:admin` (Spatie). Ensure the client stores and sends the API token consistently.

---

## 3. Global cross-cutting surfaces (every authenticated user, including admin)

These are not “admin pages” exclusively, but **must exist in the shell** for an admin user the same as for others.

### 3.1 User notifications (private + public)

- `GET /api/notifications` — shorthand private list  
- `GET /api/user/notifications/private`, `GET /api/user/notifications/public`  
- `PATCH /api/notifications/mark-all-read`, `PATCH /api/notifications/{id}/read`  
- `PATCH /api/user/notifications/mark-all-read`, `PATCH /api/user/notifications/{id}/read`  

**UI:** Notification center with tabs or filters (private vs public), read/unread states, pagination if the client implements it.

### 3.2 Internal chat

Prefix: `/api/chat/*` — conversations, messages, read receipts, unread count, `GET /api/chat/list_user` (employee list for starting chats).

**UI:** Inbox, thread view, composer, unread badge in header.

### 3.3 My tasks (system-wide)

- `GET /api/my-tasks`, `GET /api/requested-tasks`  
- `PATCH /api/my-tasks/{id}/status`  
- `POST /api/tasks`  
- `GET /api/tasks/sections`, `GET /api/tasks/sections/{section}/users`  

**UI:** Task list with status transitions; create task with section-aware assignee picker.

### 3.4 AI assistant (general)

Under `auth:sanctum` + throttle `ai-assistant`:

- Legacy: `POST /api/ai/ask`, `POST /api/ai/chat`, conversations CRUD, `GET /api/ai/sections`  
- **RAG documents:** `POST/GET/DELETE /api/ai/documents`, `POST /api/ai/documents/{id}/reindex`, `POST /api/ai/documents/search`  
- **Tool orchestrator:** `POST /api/ai/tools/chat`, `POST /api/ai/tools/stream` (and `/api/ai/v2/*` aliases)  

**UI:** Chat layout with optional document upload/manage panel for power users.

### 3.5 Assistant chat (HTTP)

- `POST /api/ai/assistant/chat` — authenticated users  

**UI:** Can be merged with the above or a separate “Help” widget.

### 3.6 Teams (generic index)

- `GET /api/teams/index`, `GET /api/teams/show/{id}`  

**UI:** Lightweight team browser for references (many flows use team pickers elsewhere).

### 3.7 Manager workspace (if admin users are also line managers)

`GET/PATCH/... /api/manager/*` — employees, reviews, tasks statistics.  
**Note:** These routes are **not** restricted to admin in `api.php`; availability depends on **business rules** in controllers/policies. If admins should not see this, hide by role; if they manage people, expose it.

### 3.8 Exclusive project requests

`GET/POST /api/exclusive-projects`, `GET /api/exclusive-projects/{id}`, `POST .../approve`, `POST .../reject`, `PUT .../contract`, `GET .../export`  

**Permissions:** `exclusive_projects.approve`, `exclusive_projects.contract.complete`, `exclusive_projects.contract.export` (admin has all).  

**UI:** Pipeline board or table: pending → approved/rejected → contract completed → export.

### 3.9 Storage file serving

`GET /api/storage/{path}` — authenticated; path validated against public disk.  

**UI:** Use for embedding PDFs/images when the API returns storage paths.

---

## 4. Admin-only API group (`/api/admin/*`)

**Middleware:** `auth:sanctum` + `role:admin`.

These routes should map to **dedicated “System administration”** navigation items (or a top-level **Settings / Governance** area).

### 4.1 Employees (Spatie roles, CRUD, soft-delete restore)

Base: `/api/admin/employees/*`

| Method | Path | Permission middleware | Designer notes |
|--------|------|-------------------------|----------------|
| GET | `/roles` | `employees.manage` | Role picker data for create/edit employee. |
| POST | `/add_employee` | `employees.manage` | Wizard or modal: identity, type, role assignment. |
| GET | `/list_employees` | `employees.manage` | Paginated table; filters (type, active, search). |
| GET | `/show_employee/{id}` | `employees.manage` | Profile + permissions summary + activity hooks (if added client-side). |
| PUT | `/update_employee/{id}` | `employees.manage` | Same as create; optimistic UI with conflict handling. |
| DELETE | `/delete_employee/{id}` | `employees.manage` | Confirm destructive action; explain impact on assignments. |
| PATCH | `/restore/{id}` | `employees.manage` | **Restore** soft-deleted employee — place near “deleted users” filter. |

**Screens to design:**

1. **Employee directory** (table, bulk actions placeholder, export placeholder if not in API).  
2. **Employee detail** (read-only sections + edit).  
3. **Create / edit employee** (multi-step if role + department fields are heavy).  
4. **Deleted employees** view (filter on list or sub-route) with **Restore** CTA.

### 4.2 Contracts (global list + status control)

| Method | Path | Permission | Notes |
|--------|------|------------|--------|
| GET | `/admin/contracts/adminIndex` | `contracts.view_all` | All contracts overview. |
| PATCH | `/admin/contracts/adminUpdateStatus/{id}` | `contracts.approve` | Status transition control. |

**Screens:**

1. **All contracts** — filters (status, developer, city/district if available in payload), row actions.  
2. **Contract status action** — modal with new status, comment (if API supports — verify controller), success refresh.

### 4.3 Notifications (admin broadcast tools)

| Method | Path | Permission | Purpose |
|--------|------|------------|---------|
| GET | `/admin/notifications` | `notifications.view` | Admin’s own notification inbox (REST). |
| POST | `/admin/notifications/send-to-user` | `notifications.manage` | Target one user. |
| POST | `/admin/notifications/send-public` | `notifications.manage` | Broadcast/public message. |
| GET | `/admin/notifications/user/{userId}` | `notifications.manage` | Support: inspect user’s notifications. |
| GET | `/admin/notifications/public` | `notifications.manage` | List public notifications. |

**Screens:**

1. **Send notification** — form: audience (single user vs public), title/body (fields per `NotificationController` contract), schedule (only if API adds it — currently immediate).  
2. **Public notifications log**  
3. **User notification inspector** (search user → view feed)

**Real-time:** Complement with **WebSocket** `admin-notifications` channel for incoming admin-targeted events.

### 4.4 Sales — project assignment (admin override)

| Method | Path | Permission |
|--------|------|------------|
| POST | `/admin/sales/project-assignments` | `sales.team.manage` |

**Validated body (server):** `team_code` (string, max 32), `contract_id` (exists), optional `start_date`, `end_date` (end ≥ start).

**Screen: “Assign project to sales team”**

- Contract selector (searchable).  
- Team code input with **validation hint** (max length).  
- Optional date range with calendar.  
- Result view: assigned leader + contract summary (API returns assignment with relations).

### 4.5 Reference data — Cities

`/api/admin/cities` — full CRUD: `GET /`, `POST /`, `GET /{id}`, `PUT|PATCH /{id}`, `DELETE /{id}`.

**Fields (create):** `name` (required, string ≤255), `code` (required, string ≤64, **unique**).

**Screen:** Cities table + create/edit modal + delete confirmation + inline error for duplicate `code`.

### 4.6 Reference data — Districts

`/api/admin/districts` — full CRUD (same HTTP pattern as cities).

**Fields (create):** `city_id` (required, exists), `name` (required, unique **per city**).

**Screen:** Districts table with **city filter** mandatory in UX (dependency: creating district requires city). Show duplicate name errors per city.

---

## 5. Admin as project management (`role:project_management|admin`)

Treat this as the **“Projects / Contracts operations”** hub.

**Contracts & status**

- `GET /api/contracts/admin-index`, `PATCH /api/contracts/update-status/{id}`  

**Second party**

- `POST/PUT /api/second-party-data/store|update/{id}`  
- `GET /api/second-party-data/second-parties`, `GET /api/second-party-data/contracts-by-email`  

**Units**

- `GET/POST/PUT/DELETE /api/contracts/units/...` + CSV `POST .../upload-csv/{contractId}`  

**Departments**

- Boards, Montage, Photography — `show/store/update` (+ `PATCH .../approve` where defined)  

**PM dashboard**

- `GET /api/project_management/dashboard`, `GET /api/project_management/dashboard/units-statistics`  

**Teams attached to contracts**

- Under `/api/project_management/teams/*` — team CRUD, members assign/remove, link teams to contracts (`add/{contractId}`, `remove/{contractId}`), contract locations per team, etc.  

**Also:** `GET /api/project_management/teams/index` is registered for broader auth — usable from pickers.

**Screens (high level):**

1. **PM dashboard** (KPIs + units statistics).  
2. **Contract pipeline** (same data as admin index but with PM-oriented filters and quick actions).  
3. **Contract detail workspace** with tabs: Info, Second party, Units (table + CSV upload), Boards, Photography, Montage, Teams.  
4. **Team management** (create team, assign members, bind to contract).  
5. **Second-party lookup** (email → contracts).

---

## 6. Admin as editor (`role:editor|admin`)

**Screens:**

- **Editor contract list** — `GET /api/editor/contracts/index`  
- **Editor contract detail** — `GET /api/editor/contracts/show/{id}`  
- **Montage** — full edit + approve on editor routes  
- **Boards / Photography** — edit paths as in editor group  
- **Developers** — `GET /api/editor/developers`, `GET /api/editor/developers/{developer_number}`  
- **Second party / units** — view-only routes under editor prefix  

Use a **media-focused** layout (large previews, status of approval).

---

## 7. Admin as sales / sales leader (`role:sales|sales_leader|admin`)

Admins inherit the **entire sales module** including leader-only subsets gated by `sales.team.manage` and related permissions.

**Core areas to design:**

1. **Sales dashboard** — `GET /api/sales/dashboard`  
2. **Projects** — list, detail, units, PDFs (`projects/*`, `units/*`)  
3. **Unit search** — `GET /api/sales/units/search`, `GET /api/sales/units/filters`  
4. **Reservations** — create, list, detail, confirm, cancel, actions, voucher  
5. **Targets** — my targets, update, **leader:** create targets  
6. **Attendance** — my attendance; **leader:** team, schedules, project overview, bulk  
7. **Team management** — projects, members, recommendations, ratings, remove member, emergency contacts  
8. **Marketing tasks (sales-created)** — projects list, task detail, create/update tasks  
9. **Waiting list** — full CRUD + **leader** convert  
10. **Insights** — sold units, commission summary, deposits management/follow-up  
11. **Analytics** — dashboard, sold-units, deposit stats by project, commission stats by employee, monthly commission report  
12. **Negotiation approvals** — under `/api/sales/negotiations/*` (separate group, same role mix)  
13. **Payment plans** — `GET/POST /api/sales/reservations/{id}/payment-plan`, installment CRUD  

**UX:** Even for admin, **respect sales workflow language** (reservations, vouchers, deposits). Provide **role toggle** in admin profile only if product requires “act as sales” vs “act as observer”; API does not distinguish.

---

## 8. Admin as marketing (`role:marketing|admin`)

Design a full **Marketing OS**:

- Dashboard, projects, budgets, developer plans, employee plans (incl. auto-generate, PDF data)  
- Expected sales + conversion rate setting  
- Tasks (view vs confirm)  
- Team assignment + recommendations  
- Marketing employees directory  
- Leads  
- Reports (project, budget, expected bookings, employee, exports)  
- Settings key/value updates  

**Ads platform** (`/api/ads/*`, role `admin|marketing`)

- Accounts, campaigns, insights, leads (+ export + snap export)  
- Sync trigger, outcomes + status  

**Screen ideas:** Marketing dashboard, project marketing cockpit, plan builders (heavy forms), **Ads analytics** with sync status and lead table.

---

## 9. Admin as HR (`HrMiddleware`: types `hr` and `admin`)

`/api/hr/*` — full HR suite:

- Employee CRUD (parallel to admin employees but HR-scoped middleware)  
- User contracts, file uploads, toggle status  
- Warnings  
- Teams (HR team CRUD + members)  
- Marketer performance  
- HR dashboard + refresh  
- Reports: team performance, marketer performance (+ PDF), employee count, expiring/ended contracts (+ PDF)  

**Screens:** HR dashboard, employee master (HR view), contracts & documents, warnings, teams, reports center with **PDF preview/download**.

---

## 10. Admin as inventory (`InventoryMiddleware`: `inventory` and `admin`)

**Screens:**

- Contract show, admin-index style listings  
- Second party show  
- Units by contract  
- **Locations** — `GET /api/inventory/contracts/locations`  
- **Agency overview** — `GET /api/inventory/contracts/agency-overview`  
- **Inventory dashboard** — `GET /api/inventory/dashboard`  

**UX:** Map or table-first for **locations**; overview cards for **agency** KPIs.

---

## 11. Admin as accounting (`role:accounting|admin`)

**Screens (each with list + detail patterns):**

- Accounting dashboard + notifications list/read  
- **Sold units & commissions** — distributions, approve/reject, confirm payment, PDF data  
- **Deposits** — pending, confirm, refund, follow-up, PDF data  
- **Down payment confirmations** — pending + history  
- **Salaries** — view, distribute, approve, mark paid  
- **Claim files** — candidates, sold units by project, download per reservation  

Use **financial table** patterns: row-level status chips, batch actions only where API supports (mostly single-item POSTs).

---

## 12. Admin as credit (`role:credit|admin`)

**Screens:**

- Credit dashboard (+ refresh)  
- **Bookings** — segmented lists: index, confirmed, negotiation, waiting, sold, cancelled + detail  
- **Booking actions** — cancel, update fields, **log credit client contact** (`POST .../actions`)  
- **Payment plans** — show/store/update/delete installments (booking id = reservation id)  
- **Financing** — view, initialize, advance, complete stage, reject  
- **Title transfer** — initialize, pending queue, schedule/unschedule, complete; sold projects list  
- **Claim files** — list, candidates, bulk/combined generate, PDF generation, per-booking generate  
- Credit notifications  

**UX:** Strong **stage/stepper** UI for financing and title transfer; **timeline** for booking actions.

---

## 13. AI calling (`role:admin|sales|sales_leader|marketing`, permission `ai-calls.manage`)

**Screens:**

- Call list + analytics  
- Scripts CRUD  
- Initiate call + bulk initiate  
- Call detail + transcript + retry  

**UX:** Compliance-forward — show **recording/transcript** access rules in the UI copy; loading states for telephony.

---

## 14. Assistant knowledge base (**admin-only**)

`/api/ai/knowledge` — `GET/POST/PUT/{id}/DELETE/{id}`

**Entry fields (create/update):** `module`, optional `page_key`, `title`, `content_md` (markdown), optional `tags[]`, optional `roles[]`, optional `permissions[]`, `language` (`ar`|`en`), optional `is_active`, optional `priority` (0–65535).

**Screens:**

1. **Knowledge library** — filters: module, page_key, language, is_active, search (title + content).  
2. **Editor** — markdown editor with preview; tag chips; priority sorter.  
3. **Versioning** — not in API; if needed later, keep UI flexible.

---

## 15. Suggested information architecture (navigation map)

Below is a **recommended** menu structure aligned with the backend. Adjust labels for Arabic product copy.

1. **Home / Cross-cutting**  
   - Personal dashboard (compose from tasks + notifications + shortcuts)  
   - Notifications  
   - Chat  
   - My tasks  

2. **Governance (admin prefix)**  
   - Employees  
   - Contract approval (global)  
   - Notification broadcaster  
   - Sales assignment (team code)  
   - Cities & districts  

3. **Projects & contracts (PM + editor overlap)**  
   - PM dashboard  
   - All contracts  
   - Contract workspace  
   - Teams  

4. **Sales**  
   - Sales dashboard  
   - Projects & units  
   - Reservations  
   - Waiting list  
   - Targets & attendance (split by leader tools)  
   - Negotiations  
   - Payment plans  
   - Analytics  

5. **Marketing**  
   - Marketing dashboard  
   - Plans & budgets  
   - Tasks & leads  
   - Reports  
   - Ads  

6. **HR**  
   - HR dashboard  
   - Employees  
   - Teams  
   - Warnings  
   - HR reports  

7. **Inventory**  
   - Inventory dashboard  
   - Locations & agency overview  

8. **Accounting**  
   - Dashboard  
   - Commissions  
   - Deposits  
   - Salaries  
   - Claim files  

9. **Credit**  
   - Dashboard  
   - Bookings (all segments)  
   - Financing  
   - Title transfer  
   - Claim files  

10. **Exclusive projects**  
    - Pipeline  

11. **AI**  
    - Assistant chat  
    - Documents (RAG)  
    - Knowledge base (admin)  
    - AI calls  

12. **Settings / profile**  
    - User profile (`/api/user`)  
    - Logout  

---

## 16. Page specification template (apply to each major screen)

For every screen, designers should deliver:

| Layer | Deliverable |
|-------|-------------|
| **User story** | Who (admin), intent, success criteria. |
| **Entry points** | Nav path + deep links (e.g. contract id). |
| **Data dependencies** | Exact API endpoints and query params (pagination, filters). |
| **Primary layout** | List vs detail vs split view; RTL behavior. |
| **Components** | Tables, filters, chips, steppers, drawers, PDF viewer, map. |
| **States** | Loading skeletons; empty (no contracts / no bookings); partial permissions (future-proof). |
| **Errors** | 401 re-login; 403 forbidden page; 422 field errors (often Arabic); 500 retry. |
| **Actions** | Primary/secondary buttons; destructive styling; confirmation modals. |
| **Accessibility** | Focus order, table semantics, live regions for toasts. |
| **Analytics** | Optional product analytics events (segment by department). |

---

## 17. Real-time and admin notifications

| Channel | Authorization | UX |
|---------|----------------|-----|
| `admin-notifications` | `user.type === 'admin'` | Bell badge + toast; persistent feed page optional. |
| `user-notifications.{userId}` | Same user id | Standard user notification center. |
| `conversation.{conversationId}` | Participant | Chat updates. |

Ensure the client **re-subscribes** on token refresh if your SPA implements refresh.

---

## 18. Explicit non-goals / gaps to validate with engineering

- This document **does not** list every JSON field of every resource; when designing tables, pull **actual JSON** from API resources in `app/Http/Resources` or OpenAPI/Postman if maintained.  
- **Manager** routes may or may not apply to admin accounts — confirm with `ManagerEmployeeController` policies.  
- Some **commented** routes in `api.php` (e.g. parts of editor photography) may be disabled; verify before designing dependent flows.  

---

## 19. Summary checklist for UI/UX delivery

- [ ] **Admin shell** with department-based nav and RTL support  
- [ ] **Employee lifecycle** (admin HR + admin-only restore)  
- [ ] **Global contract governance** (list + status patch)  
- [ ] **Notification composer** + public log + user inspector + WebSocket feed  
- [ ] **Geography reference** CRUD (cities, districts)  
- [ ] **Sales team assignment** form (team code + contract + dates)  
- [ ] Full **mirror** of PM, Sales, Marketing, HR, Inventory, Accounting, Credit, Exclusive, AI surfaces listed above  
- [ ] **Knowledge base CMS** for assistant (`content_md`, filters)  
- [ ] **AI calls** operations console  
- [ ] Consistent **pagination/filter** patterns across modules  

---

*End of specification. Derived from the Rakez ERP Laravel API routes and configuration in the repository; update this document when `routes/api.php` or role middleware changes.*
