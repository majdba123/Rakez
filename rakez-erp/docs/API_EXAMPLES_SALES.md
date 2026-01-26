# Sales Module API Reference
## Rakez ERP - Complete Sales API Documentation

**Version:** 1.0  
**Last Updated:** January 26, 2026

---

## Table of Contents

- [Overview](#overview)
- [Authentication](#authentication)
- [Permissions & Roles](#permissions--roles)
- [Dashboard API](#dashboard-api)
- [Projects API](#projects-api)
- [Reservations API](#reservations-api)
- [Targets API](#targets-api)
- [Attendance API](#attendance-api)
- [Marketing Tasks API](#marketing-tasks-api)
- [Team Management API](#team-management-api)
- [Admin API](#admin-api)
- [Error Codes](#error-codes)

---

## Overview

The Sales Module provides comprehensive APIs for managing real estate sales operations including:

- **Dashboard Analytics**: Real-time KPIs and metrics
- **Project Management**: Dynamic status computation
- **Reservation System**: Double-booking prevention with voucher generation
- **Target Management**: Goal tracking for sales teams
- **Attendance System**: Schedule management
- **Marketing Tasks**: Campaign tracking

### Base URL
```
http://localhost/api
```

### Response Format
All endpoints return JSON responses with this structure:

**Success Response:**
```json
{
    "success": true,
    "data": { },
    "message": "Optional success message"
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Error description"
}
```

---

## Authentication

### Login

**Endpoint:** `POST /login`

**Description:** Authenticate user and receive Bearer token.

**Request Body:**
```json
{
    "email": "sales@example.com",
    "password": "password"
}
```

**Success Response (200):**
```json
{
    "token": "1|abc123xyz789...",
    "user": {
        "id": 5,
        "name": "Mohammed Ali",
        "email": "sales@example.com",
        "type": "sales",
        "team": "Team Alpha"
    }
}
```

**Usage:**
Include the token in all subsequent requests:
```
Authorization: Bearer 1|abc123xyz789...
```

---

## Permissions & Roles

### Sales Employee Permissions
- `sales.dashboard.view`
- `sales.projects.view`
- `sales.reservations.create`
- `sales.reservations.view`
- `sales.reservations.confirm` (own reservations only)
- `sales.reservations.cancel` (own reservations only)
- `sales.targets.view`
- `sales.targets.update` (own targets only)
- `sales.attendance.view`

### Sales Leader Additional Permissions
- `sales.team.manage`
- `sales.attendance.manage`
- `sales.tasks.manage`

### Admin Permissions
- All sales permissions
- Can confirm/cancel any reservation
- Can assign projects to leaders

---

## Dashboard API

### Get Dashboard KPIs

**Endpoint:** `GET /sales/dashboard`

**Permission:** `sales.dashboard.view`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| scope | string | No | `me` (default), `team`, or `all` |
| from | date | No | Start date (YYYY-MM-DD) |
| to | date | No | End date (YYYY-MM-DD) |

**Examples:**

```bash
# My reservations
GET /sales/dashboard?scope=me

# Team reservations
GET /sales/dashboard?scope=team

# Date range filter
GET /sales/dashboard?from=2026-01-01&to=2026-01-31&scope=me
```

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "total_reservations": 45,
        "confirmed_count": 30,
        "negotiation_count": 10,
        "cancelled_count": 5,
        "percent_confirmed": 66.67,
        "percent_negotiation": 22.22,
        "percent_cancelled": 11.11
    }
}
```

---

## Projects API

### List Projects

**Endpoint:** `GET /sales/projects`

**Permission:** `sales.projects.view`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| per_page | integer | No | Results per page (default: 15) |
| q | string | No | Search by project name |
| city | string | No | Filter by city |
| district | string | No | Filter by district |
| status | string | No | Filter by sales_status (`available`, `pending`) |
| scope | string | No | `me`, `team`, or `all` (default: `me`) |

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "contract_id": 1,
            "project_name": "Al Noor Towers",
            "developer_name": "Rakez Development",
            "city": "Riyadh",
            "district": "Al Malqa",
            "sales_status": "available",
            "total_units": 120,
            "available_units": 85,
            "reserved_units": 35,
            "project_image_url": "https://example.com/image.jpg"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 67
    }
}
```

### Get Project Details

**Endpoint:** `GET /sales/projects/{contractId}`

**Permission:** `sales.projects.view`

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "contract_id": 1,
        "project_name": "Al Noor Towers",
        "developer_name": "Rakez Development",
        "developer_number": "+966501234567",
        "city": "Riyadh",
        "district": "Al Malqa",
        "sales_status": "available",
        "total_units": 120,
        "available_units": 85,
        "reserved_units": 35,
        "emergency_contact_number": "+966509999999",
        "security_guard_number": "+966508888888",
        "montage_data": {
            "image_url": "https://example.com/montage.jpg",
            "video_url": "https://example.com/video.mp4",
            "description": "Luxury apartments"
        },
        "created_at": "2026-01-15T10:00:00Z"
    }
}
```

**Project Status Logic:**

A project's `sales_status` is computed dynamically:

- **`pending`**: Contract not ready OR units have zero/null prices
- **`available`**: Contract is ready AND all units have prices > 0

### Get Project Units

**Endpoint:** `GET /sales/projects/{contractId}/units`

**Permission:** `sales.projects.view`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| per_page | integer | No | Results per page (default: 15) |
| floor | string | No | Filter by floor number |
| min_price | number | No | Minimum price |
| max_price | number | No | Maximum price |
| status | string | No | Filter by availability (`available`, `reserved`, `sold`) |

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "unit_id": 101,
            "unit_number": "A-101",
            "floor": "1",
            "area": "120",
            "price": 500000,
            "computed_availability": "available",
            "can_reserve": true,
            "active_reservation": null
        },
        {
            "unit_id": 102,
            "unit_number": "A-102",
            "floor": "1",
            "area": "150",
            "price": 650000,
            "computed_availability": "reserved",
            "can_reserve": false,
            "active_reservation": {
                "id": 25,
                "status": "confirmed",
                "client_name": "Ahmed Abdullah"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 6,
        "per_page": 20,
        "total": 120
    }
}
```

---

## Reservations API

### Get Reservation Context

**Endpoint:** `GET /sales/units/{unitId}/reservation-context`

**Permission:** `sales.reservations.create`

**Description:** Returns all necessary data to create a reservation including project info, unit details, and lookup values.

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "project": {
            "contract_id": 1,
            "project_name": "Al Noor Towers",
            "developer_name": "Rakez Development"
        },
        "unit": {
            "unit_id": 101,
            "unit_number": "A-101",
            "floor": "1",
            "area": "120",
            "price": 500000
        },
        "marketing_employee": {
            "id": 5,
            "name": "Mohammed Ali",
            "team": "Team Alpha"
        },
        "lookups": {
            "reservation_types": [
                {"value": "confirmed_reservation", "label": "Confirmed Reservation"},
                {"value": "negotiation", "label": "Under Negotiation"}
            ],
            "payment_methods": [
                {"value": "bank_transfer", "label": "Bank Transfer"},
                {"value": "cash", "label": "Cash"},
                {"value": "bank_financing", "label": "Bank Financing"}
            ],
            "down_payment_statuses": [
                {"value": "refundable", "label": "Refundable"},
                {"value": "non_refundable", "label": "Non-Refundable"}
            ],
            "purchase_mechanisms": [
                {"value": "cash", "label": "Cash Purchase"},
                {"value": "supported_bank", "label": "Supported Bank Financing"},
                {"value": "unsupported_bank", "label": "Unsupported Bank Financing"}
            ],
            "nationalities": [
                {"value": "saudi", "label": "Saudi"},
                {"value": "egyptian", "label": "Egyptian"},
                {"value": "jordanian", "label": "Jordanian"}
            ]
        }
    }
}
```

### Create Reservation

**Endpoint:** `POST /sales/reservations`

**Permission:** `sales.reservations.create`

**Request Body:**
```json
{
    "contract_id": 1,
    "contract_unit_id": 101,
    "contract_date": "2026-02-01",
    "reservation_type": "negotiation",
    "negotiation_notes": "Client needs more time to decide on financing options",
    "client_name": "Ahmed Abdullah",
    "client_mobile": "+966501234567",
    "client_nationality": "saudi",
    "client_iban": "SA0380000000608010167519",
    "payment_method": "bank_financing",
    "down_payment_amount": 50000,
    "down_payment_status": "refundable",
    "purchase_mechanism": "supported_bank"
}
```

**Validation Rules:**

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| contract_id | integer | Yes | Must exist in contracts table |
| contract_unit_id | integer | Yes | Must exist in contract_units table |
| contract_date | date | Yes | Valid date format |
| reservation_type | string | Yes | `confirmed_reservation` or `negotiation` |
| negotiation_notes | string | Conditional | Required if reservation_type is `negotiation` |
| client_name | string | Yes | Max 255 characters |
| client_mobile | string | Yes | Max 50 characters |
| client_nationality | string | Yes | Max 100 characters |
| client_iban | string | Yes | Max 100 characters |
| payment_method | string | Yes | `bank_transfer`, `cash`, or `bank_financing` |
| down_payment_amount | number | Yes | Minimum 0 |
| down_payment_status | string | Yes | `refundable` or `non_refundable` |
| purchase_mechanism | string | Yes | `cash`, `supported_bank`, or `unsupported_bank` |

**Success Response (201):**
```json
{
    "success": true,
    "message": "Reservation created successfully",
    "data": {
        "id": 25,
        "contract_id": 1,
        "contract_unit_id": 101,
        "marketing_employee_id": 5,
        "status": "under_negotiation",
        "reservation_type": "negotiation",
        "contract_date": "2026-02-01",
        "client_name": "Ahmed Abdullah",
        "client_mobile": "+966501234567",
        "voucher_pdf_path": "vouchers/reservation_25_1738000000.pdf",
        "created_at": "2026-01-26T12:00:00Z"
    }
}
```

**Double Booking Error (409):**
```json
{
    "success": false,
    "message": "Unit already has an active reservation"
}
```

**Features:**
- ✅ Double-booking prevention with database row locking
- ✅ Automatic voucher PDF generation
- ✅ Snapshot of project/unit data at reservation time
- ✅ Real-time notifications to departments
- ✅ Unit status automatically updated to 'reserved'

### List Reservations

**Endpoint:** `GET /sales/reservations`

**Permission:** `sales.reservations.view`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| mine | boolean | No | Show only my reservations (1 or 0) |
| per_page | integer | No | Results per page (default: 15) |
| status | string | No | Filter by status |
| contract_id | integer | No | Filter by project |
| from | date | No | Start date |
| to | date | No | End date |
| include_cancelled | boolean | No | Include cancelled reservations (1 or 0) |

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 25,
            "contract_id": 1,
            "project_name": "Al Noor Towers",
            "unit_number": "A-101",
            "client_name": "Ahmed Abdullah",
            "status": "under_negotiation",
            "reservation_type": "negotiation",
            "down_payment_amount": 50000,
            "created_at": "2026-01-26T12:00:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 2,
        "per_page": 15,
        "total": 23
    }
}
```

### Confirm Reservation

**Endpoint:** `POST /sales/reservations/{id}/confirm`

**Permission:** `sales.reservations.confirm`

**Authorization Rules:**
- Employees can only confirm their OWN reservations
- Admins can confirm ANY reservation

**Success Response (200):**
```json
{
    "success": true,
    "message": "Reservation confirmed successfully",
    "data": {
        "id": 25,
        "status": "confirmed",
        "confirmed_at": "2026-01-26T14:30:00Z"
    }
}
```

**Unauthorized Error (403):**
```json
{
    "success": false,
    "message": "Failed to confirm reservation: Unauthorized to confirm this reservation"
}
```

**Invalid Status Error (400):**
```json
{
    "success": false,
    "message": "Failed to confirm reservation: Reservation cannot be confirmed in current status"
}
```

### Cancel Reservation

**Endpoint:** `POST /sales/reservations/{id}/cancel`

**Permission:** `sales.reservations.cancel`

**Request Body:**
```json
{
    "cancellation_reason": "Client withdrew from purchase"
}
```

**Authorization Rules:**
- Employees can only cancel their OWN reservations
- Admins can cancel ANY reservation

**Success Response (200):**
```json
{
    "success": true,
    "message": "Reservation cancelled successfully",
    "data": {
        "id": 25,
        "status": "cancelled",
        "cancelled_at": "2026-01-26T15:00:00Z"
    }
}
```

**Features:**
- ✅ Unit status reverted to 'available'
- ✅ Multiple cancelled reservations allowed per unit
- ✅ Cancellation reason logged

### Log Action on Reservation

**Endpoint:** `POST /sales/reservations/{id}/actions`

**Permission:** `sales.reservations.view`

**Request Body:**
```json
{
    "action_type": "client_called",
    "notes": "Called client to confirm meeting time. Agreed on 2PM tomorrow."
}
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "Action logged successfully",
    "data": {
        "action_id": 52,
        "action_type": "client_called",
        "created_at": "2026-01-26T16:00:00Z"
    }
}
```

### Download Voucher PDF

**Endpoint:** `GET /sales/reservations/{id}/voucher`

**Permission:** `sales.reservations.view`

**Authorization Rules:**
- Employees can only download their OWN reservation vouchers
- Admins can download ANY voucher

**Success Response (200):**
Returns PDF file with:
```
Content-Type: application/pdf
Content-Disposition: attachment; filename="reservation_25_voucher.pdf"
```

**Not Found Error (404):**
```json
{
    "success": false,
    "message": "Voucher not found"
}
```

---

## Targets API

### View My Targets

**Endpoint:** `GET /sales/targets/my`

**Permission:** `sales.targets.view`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| per_page | integer | No | Results per page (default: 15) |
| status | string | No | Filter by status (`new`, `in_progress`, `completed`) |
| from | date | No | Start date |
| to | date | No | End date |

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 15,
            "leader_id": 8,
            "leader_name": "Fahad Mohammed",
            "contract_id": 1,
            "project_name": "Al Noor Towers",
            "unit_number": "A-102",
            "target_type": "reservation",
            "status": "in_progress",
            "start_date": "2026-02-01",
            "end_date": "2026-02-28",
            "notes": "High priority unit",
            "created_at": "2026-01-25T10:00:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 8
    }
}
```

### Create Target (Leader Only)

**Endpoint:** `POST /sales/targets`

**Permission:** `sales.team.manage`

**Request Body:**
```json
{
    "marketer_id": 10,
    "contract_id": 1,
    "contract_unit_id": 102,
    "target_type": "reservation",
    "start_date": "2026-02-01",
    "end_date": "2026-02-28",
    "notes": "High priority unit - focus on this client"
}
```

**Note:** `contract_unit_id` is optional for project-level targets.

**Success Response (201):**
```json
{
    "success": true,
    "message": "Target created successfully",
    "data": {
        "id": 16,
        "leader_id": 8,
        "marketer_id": 10,
        "marketer_name": "Ali Hassan",
        "contract_id": 1,
        "project_name": "Al Noor Towers",
        "unit_number": "A-102",
        "target_type": "reservation",
        "status": "new",
        "start_date": "2026-02-01",
        "end_date": "2026-02-28"
    }
}
```

### Update Target Status

**Endpoint:** `PATCH /sales/targets/{id}`

**Permission:** `sales.targets.update`

**Authorization:** Marketers can only update their OWN targets

**Request Body:**
```json
{
    "status": "in_progress",
    "notes": "Met with client, negotiation ongoing"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Target updated successfully",
    "data": {
        "id": 16,
        "status": "in_progress",
        "notes": "Met with client, negotiation ongoing",
        "updated_at": "2026-01-26T11:00:00Z"
    }
}
```

---

## Attendance API

### View My Attendance

**Endpoint:** `GET /sales/attendance/my`

**Permission:** `sales.attendance.view`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| from | date | No | Start date |
| to | date | No | End date |

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 42,
            "contract_id": 1,
            "project_name": "Al Noor Towers",
            "schedule_date": "2026-02-05",
            "start_time": "09:00:00",
            "end_time": "17:00:00",
            "notes": "Project site visit day",
            "created_by_name": "Fahad Mohammed"
        }
    ]
}
```

### View Team Attendance (Leader Only)

**Endpoint:** `GET /sales/attendance/team`

**Permission:** `sales.attendance.manage`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| from | date | No | Start date |
| to | date | No | End date |
| contract_id | integer | No | Filter by project |
| user_id | integer | No | Filter by team member |

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 42,
            "user_id": 10,
            "user_name": "Ali Hassan",
            "contract_id": 1,
            "project_name": "Al Noor Towers",
            "schedule_date": "2026-02-05",
            "start_time": "09:00:00",
            "end_time": "17:00:00",
            "notes": "Project site visit day"
        }
    ]
}
```

### Create Schedule (Leader Only)

**Endpoint:** `POST /sales/attendance/schedules`

**Permission:** `sales.attendance.manage`

**Request Body:**
```json
{
    "contract_id": 1,
    "user_id": 10,
    "schedule_date": "2026-02-05",
    "start_time": "09:00:00",
    "end_time": "17:00:00",
    "notes": "Project site visit day"
}
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "Schedule created successfully",
    "data": {
        "id": 43,
        "contract_id": 1,
        "user_id": 10,
        "schedule_date": "2026-02-05",
        "start_time": "09:00:00",
        "end_time": "17:00:00"
    }
}
```

---

## Marketing Tasks API

### List Task Projects (Leader Only)

**Endpoint:** `GET /sales/tasks/projects`

**Permission:** `sales.tasks.manage`

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "contract_id": 1,
            "project_name": "Al Noor Towers",
            "city": "Riyadh",
            "district": "Al Malqa",
            "active_tasks_count": 3
        }
    ]
}
```

### View Project for Tasks (Leader Only)

**Endpoint:** `GET /sales/tasks/projects/{contractId}`

**Permission:** `sales.tasks.manage`

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "contract_id": 1,
        "project_name": "Al Noor Towers",
        "project_description": "Luxury residential towers",
        "montage_designs": {
            "image_url": "https://example.com/montage.jpg",
            "video_url": "https://example.com/video.mp4",
            "description": "Modern architecture design"
        }
    }
}
```

### Create Marketing Task (Leader Only)

**Endpoint:** `POST /sales/marketing-tasks`

**Permission:** `sales.tasks.manage`

**Request Body:**
```json
{
    "contract_id": 1,
    "task_name": "Social Media Campaign - February",
    "marketer_id": 10,
    "participating_marketers_count": 4,
    "description": "Create and publish content for Instagram and Twitter",
    "start_date": "2026-02-01",
    "end_date": "2026-02-28"
}
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "Marketing task created successfully",
    "data": {
        "id": 8,
        "contract_id": 1,
        "project_name": "Al Noor Towers",
        "task_name": "Social Media Campaign - February",
        "marketer_id": 10,
        "marketer_name": "Ali Hassan",
        "participating_marketers_count": 4,
        "status": "new",
        "start_date": "2026-02-01",
        "end_date": "2026-02-28"
    }
}
```

### Update Task Status (Leader Only)

**Endpoint:** `PATCH /sales/marketing-tasks/{id}`

**Permission:** `sales.tasks.manage`

**Request Body:**
```json
{
    "status": "in_progress"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Task updated successfully",
    "data": {
        "id": 8,
        "status": "in_progress",
        "updated_at": "2026-01-26T12:00:00Z"
    }
}
```

---

## Team Management API

### Get Team Members (Leader Only)

**Endpoint:** `GET /sales/team/members`

**Permission:** `sales.team.manage`

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 10,
            "name": "Ali Hassan",
            "email": "ali.hassan@example.com",
            "team": "Team Alpha"
        },
        {
            "id": 11,
            "name": "Sara Ahmed",
            "email": "sara.ahmed@example.com",
            "team": "Team Alpha"
        }
    ]
}
```

### Update Emergency Contacts (Leader Only)

**Endpoint:** `PATCH /sales/projects/{contractId}/emergency-contacts`

**Permission:** `sales.team.manage`

**Request Body:**
```json
{
    "emergency_contact_number": "+966509999999",
    "security_guard_number": "+966508888888"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Emergency contacts updated successfully",
    "data": {
        "contract_id": 1,
        "emergency_contact_number": "+966509999999",
        "security_guard_number": "+966508888888"
    }
}
```

### Get Team Projects (Leader Only)

**Endpoint:** `GET /sales/team/projects`

**Permission:** `sales.team.manage`

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "contract_id": 1,
            "project_name": "Al Noor Towers",
            "sales_status": "available",
            "total_units": 120,
            "available_units": 85,
            "reserved_units": 35
        }
    ]
}
```

---

## Admin API

### Assign Project to Leader (Admin Only)

**Endpoint:** `POST /admin/sales/project-assignments`

**Permission:** `sales.team.manage` + `admin` role

**Request Body:**
```json
{
    "leader_id": 8,
    "contract_id": 1
}
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "Project assigned successfully",
    "data": {
        "id": 12,
        "leader_id": 8,
        "leader_name": "Fahad Mohammed",
        "contract_id": 1,
        "project_name": "Al Noor Towers",
        "assigned_by": 1,
        "assigned_by_name": "Admin User",
        "created_at": "2026-01-26T10:00:00Z"
    }
}
```

---

## Error Codes

### HTTP Status Codes

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | OK | Request successful |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Invalid request data or business logic error |
| 401 | Unauthorized | Missing or invalid authentication token |
| 403 | Forbidden | User lacks required permissions |
| 404 | Not Found | Resource does not exist |
| 409 | Conflict | Resource conflict (e.g., double booking) |
| 422 | Unprocessable Entity | Validation errors |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |

### Common Error Messages

**Validation Error (422):**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "client_name": ["The client name field is required."],
        "client_mobile": ["The client mobile field is required."]
    }
}
```

**Unauthorized (401):**
```json
{
    "message": "Unauthenticated."
}
```

**Forbidden (403):**
```json
{
    "success": false,
    "message": "Unauthorized to perform this action."
}
```

**Not Found (404):**
```json
{
    "success": false,
    "message": "Project not found: No query results for model [Contract] 999"
}
```

**Double Booking (409):**
```json
{
    "success": false,
    "message": "Unit already has an active reservation"
}
```

---

## Testing

### Running Tests

```bash
# All sales tests
php artisan test --filter=Sales

# Specific test file
php artisan test tests/Feature/Sales/SalesReservationTest.php

# Specific test method
php artisan test --filter=test_create_reservation_generates_voucher_pdf
```

### Test Coverage

- ✅ 98 passing tests
- ✅ 249 assertions
- ✅ Duration: ~18 seconds

### Key Test Scenarios

1. **Dashboard KPIs** - Filtering by scope and date range
2. **Project Status** - Dynamic computation based on units
3. **Double Booking Prevention** - Concurrent reservation attempts
4. **Authorization** - Role-based access control
5. **Reservations** - Creation, confirmation, cancellation
6. **Voucher Generation** - PDF creation
7. **Targets** - Leader assignment and marketer updates
8. **Attendance** - Schedule management
9. **Marketing Tasks** - Task lifecycle

---

## Rate Limiting

All endpoints are subject to rate limiting:

- **Default**: 60 requests per minute
- **AI Assistant**: 30 requests per minute (separate endpoint group)

Rate limit headers included in response:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1738000000
```

---

## Postman Collection

Import the complete Postman collection:
- **File**: `POSTMAN_SALES_COLLECTION.json`
- **Location**: `docs/POSTMAN_SALES_COLLECTION.json`

The collection includes:
- ✅ All 40+ endpoints
- ✅ Environment variables setup
- ✅ Pre-request scripts for authentication
- ✅ Test assertions
- ✅ Example responses

---

## Support & Documentation

- **Arabic Documentation**: [SALES_AI_REPORT_AR.md](./SALES_AI_REPORT_AR.md)
- **AI Assistant API**: [API_EXAMPLES_AI.md](./API_EXAMPLES_AI.md)
- **Postman Collection**: [POSTMAN_SALES_COLLECTION.json](./POSTMAN_SALES_COLLECTION.json)

---

**Last Updated:** January 26, 2026  
**Version:** 1.0  
**Maintained by:** Rakez ERP Development Team
