# Rakez ERP - API Collection Guide

Complete guide for importing and using the Postman API collection for Rakez ERP.

## Files

| File | Path | Description |
|------|------|-------------|
| Collection | `docs/postman/Rakez.postman_collection.json` | 340 endpoints across 19 modules |
| Environment | `docs/postman/Rakez.local.postman_environment.json` | Local dev environment variables |
| Generator | `scripts/generate-postman-collection.cjs` | Regenerate collection from route defs |

## Quick Start

### 1. Import into Postman

1. Open Postman → **Import** (top-left)
2. Drag or browse to `docs/postman/Rakez.postman_collection.json`
3. Import `docs/postman/Rakez.local.postman_environment.json`
4. Select **Rakez ERP - Local** from the environment dropdown (top-right)

### 2. Get Auth Token

1. Open **01 - Auth → Login**
2. The request body uses `{{user_email}}` and `{{user_password}}` from the environment
3. Click **Send**
4. On success, the test script automatically saves `auth_token` and `user_id` to the environment

All subsequent requests inherit the collection-level **Bearer Token** auth using `{{auth_token}}`.

### 3. Test Any Endpoint

After logging in, navigate to any folder and send requests. Variables like `{{contract_id}}`, `{{unit_id}}`, etc. must be set manually or via test scripts.

## Authentication

| Property | Value |
|----------|-------|
| Method | **Laravel Sanctum** (token-based) |
| Header | `Authorization: Bearer <token>` |
| Login endpoint | `POST /api/login` |
| Logout endpoint | `POST /api/logout` |
| Token format | Opaque string returned in login response |

The Login request's **Tests** tab auto-extracts the token:

```javascript
if (pm.response.code === 200) {
    const d = pm.response.json();
    pm.environment.set('auth_token', d.data.token || d.token);
    pm.environment.set('user_id', d.data.user.id);
}
```

## RBAC (Role-Based Access Control)

Rakez uses **Spatie Permission** combined with custom type-based middleware. Each endpoint's description in Postman annotates the required **Role** and **Permission**.

### Roles

| Role | Middleware | Description |
|------|-----------|-------------|
| `admin` | `role:admin` | Full access |
| `project_management` | `role:project_management\|admin` | Contract & project ops |
| `editor` | `role:editor\|admin` | Montage department |
| `sales` | `role:sales\|sales_leader\|admin\|project_management` | Sales operations |
| `sales_leader` | (same group as sales) | Team management, targets |
| `hr` | Permission-based only | HR department |
| `marketing` | `role:marketing\|admin` | Marketing department |
| `credit` | `role:credit\|admin` | Credit & financing |
| `accounting` | `role:accounting\|admin` | Accounting department |
| `inventory` | Custom `InventoryMiddleware` | Inventory operations |

### Permission Examples

| Permission | Used By |
|-----------|---------|
| `contracts.view_all` | Admin contract index |
| `contracts.approve` | Approve/reject contracts |
| `sales.reservations.create` | Create reservations |
| `sales.team.manage` | Sales team leader functions |
| `hr.employees.manage` | User CRUD in HR |
| `marketing.budgets.manage` | Budget/expected sales |
| `credit.financing.manage` | Initialize/advance financing |
| `accounting.commissions.approve` | Commission approval |
| `use-ai-assistant` | AI chat endpoints |
| `manage-ai-knowledge` | AI knowledge CRUD |

## Collection Structure (19 Modules)

| # | Module | Endpoints | Description |
|---|--------|-----------|-------------|
| 01 | Auth | 4 | Login, logout, health, current user |
| 02 | Notifications | 12 | Proxy, user, admin notifications |
| 03 | Chat | 7 | Real-time conversations |
| 04 | Tasks | 3 | System-wide task management |
| 05 | Teams (Global) | 2 | Global team listing |
| 06 | Contracts | 30+ | CRUD, units, departments, second party |
| 07 | Developers | 2 | Developer listings |
| 08 | Project Management | 15+ | Dashboard, projects, contracts, teams |
| 09 | Editor | 2 | Editor contract access |
| 10 | Inventory | 6 | Inventory contract/unit views |
| 11 | Sales | 55+ | Dashboard, reservations, targets, attendance, waiting list, negotiations, payment plans |
| 12 | Sales Analytics | 35+ | Analytics, commissions, deposits |
| 13 | HR | 40+ | Dashboard, teams, users, warnings, contracts, reports |
| 14 | Marketing | 35+ | Dashboard, projects, plans, budget, tasks, leads, reports |
| 15 | Credit & Financing | 35+ | Dashboard, bookings, financing, title transfer, claim files |
| 16 | Accounting | 25+ | Dashboard, sold units, commissions, deposits, salaries |
| 17 | Exclusive Projects | 7 | Request, approve, contract |
| 18 | AI Assistant | 12 | V1, V2 (Rakiz), knowledge-based assistant |
| 19 | Storage | 1 | File access |

## Environment Variables

### Credentials (set before first use)

| Variable | Default | Description |
|----------|---------|-------------|
| `base_url` | `http://localhost:8000/api` | API base URL |
| `user_email` | `admin@rakez.com` | Login email |
| `user_password` | `password123` | Login password |

### Auto-populated (set by test scripts)

| Variable | Set By |
|----------|--------|
| `auth_token` | Login response |
| `user_id` | Login response |

### Manual (set as needed)

| Variable | Example | Used By |
|----------|---------|---------|
| `contract_id` | `1` | Contract endpoints |
| `unit_id` | `5` | Unit endpoints |
| `reservation_id` | `3` | Reservation endpoints |
| `booking_id` | `3` | Credit booking endpoints |
| `commission_id` | `2` | Commission endpoints |
| `deposit_id` | `1` | Deposit endpoints |
| `team_id` | `1` | Team endpoints |
| `employee_id` | `4` | HR user endpoints |
| `project_id` | `1` | Marketing project endpoints |
| `session_id` | `uuid` | AI chat sessions |
| `transfer_id` | `1` | Title transfer endpoints |
| `claim_file_id` | `1` | Claim file endpoints |
| `stage_number` | `1` | Credit financing stages |
| `lead_id` | `1` | Marketing lead endpoints |
| `knowledge_id` | `1` | AI knowledge endpoints |
| `exclusive_project_id` | `1` | Exclusive project endpoints |

## Regenerating the Collection

If routes change, regenerate the collection:

```bash
node scripts/generate-postman-collection.cjs
```

The script reads route metadata defined inline and outputs `docs/postman/Rakez.postman_collection.json`. Add new endpoints to the script's route definitions when routes are added.

## Testing Workflows

### Typical Sales Flow

1. **Login** → token saved
2. **Sales > Projects > List Projects** → get `contract_id`
3. **Sales > Projects > Project Units** → get `unit_id`
4. **Sales > Reservations > Create Reservation** → get `reservation_id`
5. **Sales > Reservations > Confirm** → confirm booking
6. **Sales Analytics > Deposits > Create Deposit** → create deposit

### Typical HR Flow

1. **Login** as HR/admin
2. **HR > Users > List Users** → get `employee_id`
3. **HR > Users > Create User** → create employee
4. **HR > Teams > Create Team** → get `team_id`
5. **HR > Teams > Assign Member** → add user to team

### Typical Credit Flow

1. **Login** as credit/admin
2. **Credit > Bookings > Confirmed** → get `booking_id`
3. **Credit > Financing > Initialize** → start financing
4. **Credit > Financing > Complete Stage** → advance through stages
5. **Credit > Title Transfer > Initialize** → start title transfer

## API Response Format

All API responses follow a consistent JSON structure:

```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

Error responses:

```json
{
  "success": false,
  "message": "Error description",
  "errors": { ... }
}
```

| Status Code | Meaning |
|-------------|---------|
| 200 | Success |
| 201 | Created |
| 401 | Unauthenticated |
| 403 | Unauthorized (missing role/permission) |
| 404 | Not found |
| 422 | Validation error |
| 500 | Server error |

## SSE / Streaming Endpoints

The AI V2 chat endpoint (`POST /api/ai/v2/chat`) may return streaming responses. Postman has limited SSE support. To test streaming:

```bash
curl -N -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -H "Accept: text/event-stream" \
     -d '{"message":"كم عدد الوحدات؟"}' \
     http://localhost:8000/api/ai/v2/chat
```

## Existing Per-Module Collections

Older per-module collections exist in `docs/postman/collections/` for reference. The unified collection (`Rakez.postman_collection.json`) supersedes them with complete coverage.
