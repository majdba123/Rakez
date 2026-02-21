# Marketing Module API Documentation

## Overview
The Marketing Module manages marketing campaigns, plans, budgets, and team assignments for real estate projects.

**Base URL:** `/api/marketing`  
**Authentication:** Required (Sanctum Token)  
**Authorization:** Marketing role or Admin

---

## Table of Contents
1. [Dashboard](#dashboard)
2. [Projects](#projects)
3. [Developer Plans](#developer-plans)
4. [Employee Plans](#employee-plans)
5. [Expected Sales](#expected-sales)
6. [Tasks](#tasks)
7. [Team Management](#team-management)
8. [Leads](#leads)
9. [Reports](#reports)
10. [Settings](#settings)

---

## Dashboard

### Get Marketing Dashboard
**Endpoint:** `GET /api/marketing/dashboard`  
**Permission:** `marketing.dashboard.view`

**Response:**
```json
{
  "success": true,
  "data": {
    "total_projects": 15,
    "active_campaigns": 8,
    "total_budget": 500000.00,
    "spent_budget": 320000.00,
    "expected_bookings": 120,
    "actual_bookings": 85,
    "conversion_rate": 2.5,
    "roi": 180.5
  }
}
```

---

## Projects

### List Marketing Projects
**Endpoint:** `GET /api/marketing/projects`  
**Permission:** `marketing.projects.view`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "contract_id": 5,
      "project_name": "مشروع برج الراكز",
      "assigned_team_leader": 3,
      "status": "active",
      "created_at": "2026-01-15T10:00:00Z"
    }
  ]
}
```

### Get Project Details
**Endpoint:** `GET /api/marketing/projects/{contractId}`  
**Permission:** `marketing.projects.view`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "contract_id": 5,
    "project_name": "مشروع برج الراكز",
    "developer_name": "شركة التطوير",
    "city": "الرياض",
    "total_units": 50,
    "duration_status": {
      "total_days": 180,
      "elapsed_days": 45,
      "remaining_days": 135,
      "percentage": 25
    }
  }
}
```

### Calculate Campaign Budget
**Endpoint:** `POST /api/marketing/projects/calculate-budget`  
**Permission:** `marketing.budgets.manage`

**Request:**
```json
{
  "contract_id": 5,
  "marketing_value": 100000,
  "average_cpm": 15.50,
  "average_cpc": 2.30,
  "conversion_rate": 2.5
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_budget": 100000,
    "impressions": 6451612,
    "clicks": 43478,
    "expected_conversions": 1087,
    "cost_per_conversion": 92.00
  }
}
```

---

## Developer Plans

### Get Developer Plan
**Endpoint:** `GET /api/marketing/developer-plans/{contractId}`  
**Permission:** `marketing.plans.create`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "contract_id": 5,
    "marketing_value": 100000,
    "average_cpm": 15.50,
    "average_cpc": 2.30,
    "conversion_rate": 2.5,
    "expected_bookings": 1087,
    "notes": "Campaign targeting Riyadh residents"
  }
}
```

### Create/Update Developer Plan
**Endpoint:** `POST /api/marketing/developer-plans`  
**Permission:** `marketing.plans.create`

**Request:**
```json
{
  "contract_id": 5,
  "marketing_value": 100000,
  "average_cpm": 15.50,
  "average_cpc": 2.30,
  "conversion_rate": 2.5,
  "expected_bookings": 1087,
  "notes": "Campaign targeting Riyadh residents"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Developer marketing plan saved successfully",
  "data": {
    "id": 1,
    "contract_id": 5,
    "marketing_value": 100000
  }
}
```

---

## Employee Plans

### List Employee Plans by Project
**Endpoint:** `GET /api/marketing/employee-plans/project/{projectId}`  
**Permission:** `marketing.plans.create`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "marketing_project_id": 1,
      "user_id": 5,
      "user": {
        "id": 5,
        "name": "أحمد محمد",
        "email": "ahmed@example.com"
      },
      "daily_target": 10,
      "weekly_target": 50,
      "monthly_target": 200
    }
  ]
}
```

### Get Employee Plan Details
**Endpoint:** `GET /api/marketing/employee-plans/{planId}`  
**Permission:** `marketing.plans.create`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "marketing_project_id": 1,
    "user_id": 5,
    "user": {
      "id": 5,
      "name": "أحمد محمد"
    },
    "campaigns": [
      {
        "id": 1,
        "name": "Facebook Campaign",
        "budget": 5000,
        "status": "active"
      }
    ]
  }
}
```

### Create Employee Plan
**Endpoint:** `POST /api/marketing/employee-plans`  
**Permission:** `marketing.plans.create`

**Request:**
```json
{
  "marketing_project_id": 1,
  "user_id": 5,
  "daily_target": 10,
  "weekly_target": 50,
  "monthly_target": 200
}
```

**Response:**
```json
{
  "success": true,
  "message": "Employee marketing plan created successfully",
  "data": {
    "id": 1,
    "marketing_project_id": 1,
    "user_id": 5
  }
}
```

### Auto-Generate Employee Plan
**Endpoint:** `POST /api/marketing/employee-plans/auto-generate`  
**Permission:** `marketing.plans.create`

**Request:**
```json
{
  "marketing_project_id": 1,
  "user_id": 5
}
```

**Response:**
```json
{
  "success": true,
  "message": "Employee marketing plan auto-generated successfully",
  "data": {
    "id": 1,
    "daily_target": 8,
    "weekly_target": 40,
    "monthly_target": 160
  }
}
```

---

## Expected Sales

### Calculate Expected Sales
**Endpoint:** `GET /api/marketing/expected-sales/{projectId}`  
**Permission:** `marketing.budgets.manage`

**Query Parameters:**
- `marketing_value` (optional): Marketing budget
- `average_cpm` (optional): Average CPM
- `average_cpc` (optional): Average CPC
- `conversion_rate` (optional): Conversion rate percentage

**Response:**
```json
{
  "success": true,
  "data": {
    "project_id": 1,
    "expected_bookings": 1087,
    "total_impressions": 6451612,
    "total_clicks": 43478,
    "conversion_rate": 2.5
  }
}
```

### Update Conversion Rate
**Endpoint:** `PUT /api/marketing/settings/conversion-rate`  
**Permission:** `marketing.budgets.manage`

**Request:**
```json
{
  "value": 2.5
}
```

**Response:**
```json
{
  "success": true,
  "message": "Conversion rate updated successfully",
  "data": {
    "key": "conversion_rate",
    "value": "2.5",
    "description": "Default conversion rate for marketing"
  }
}
```

---

## Tasks

### List Daily Tasks
**Endpoint:** `GET /api/marketing/tasks`  
**Permission:** `marketing.tasks.view`

**Query Parameters:**
- `date` (optional): Date in Y-m-d format (defaults to today)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "contract_id": 5,
      "task_name": "Create Facebook Ad Campaign",
      "marketer_id": 5,
      "due_date": "2026-02-01",
      "priority": "high",
      "status": "in_progress",
      "description": "Launch new campaign for project"
    }
  ]
}
```

### Create Task
**Endpoint:** `POST /api/marketing/tasks`  
**Permission:** `marketing.tasks.confirm`

**Request:**
```json
{
  "contract_id": 5,
  "marketing_project_id": 1,
  "task_name": "Create Facebook Ad Campaign",
  "marketer_id": 5,
  "due_date": "2026-02-01",
  "priority": "high",
  "description": "Launch new campaign for project"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Marketing task created successfully",
  "data": {
    "id": 1,
    "task_name": "Create Facebook Ad Campaign",
    "status": "new"
  }
}
```

### Update Task
**Endpoint:** `PUT /api/marketing/tasks/{taskId}`  
**Permission:** `marketing.tasks.confirm`

**Request:**
```json
{
  "task_name": "Updated Task Name",
  "priority": "medium",
  "description": "Updated description"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Marketing task updated successfully",
  "data": {
    "id": 1,
    "task_name": "Updated Task Name"
  }
}
```

### Update Task Status
**Endpoint:** `PATCH /api/marketing/tasks/{taskId}/status`  
**Permission:** `marketing.tasks.confirm`

**Request:**
```json
{
  "status": "completed"
}
```

**Valid Status Values:**
- `new`
- `in_progress`
- `completed`
- `cancelled`

**Response:**
```json
{
  "success": true,
  "message": "Task status updated successfully",
  "data": {
    "id": 1,
    "status": "completed"
  }
}
```

---

## Team Management

### Assign Team to Project
**Endpoint:** `POST /api/marketing/projects/{projectId}/team`  
**Permission:** `marketing.projects.view`

**Request:**
```json
{
  "user_ids": [5, 8, 12]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Team assigned successfully",
  "data": [
    {
      "id": 1,
      "marketing_project_id": 1,
      "user_id": 5,
      "user": {
        "id": 5,
        "name": "أحمد محمد"
      }
    }
  ]
}
```

### Get Project Team
**Endpoint:** `GET /api/marketing/projects/{projectId}/team`  
**Permission:** `marketing.projects.view`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 5,
      "user": {
        "id": 5,
        "name": "أحمد محمد",
        "email": "ahmed@example.com",
        "type": "marketing"
      }
    }
  ]
}
```

### Recommend Employee for Project
**Endpoint:** `GET /api/marketing/projects/{projectId}/recommend-employee`  
**Permission:** `marketing.projects.view`

**Response:**
```json
{
  "success": true,
  "data": {
    "user_id": 5,
    "name": "أحمد محمد",
    "email": "ahmed@example.com",
    "current_projects": 2,
    "performance_score": 92.5,
    "recommendation_reason": "Low workload and high performance"
  }
}
```

---

## Leads

### List Leads
**Endpoint:** `GET /api/marketing/leads`  
**Permission:** `marketing.projects.view`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "محمد أحمد",
      "contact_info": "0501234567",
      "source": "Facebook",
      "status": "new",
      "project_id": 5,
      "assigned_to": 8,
      "created_at": "2026-02-01T10:00:00Z"
    }
  ]
}
```

### Create Lead
**Endpoint:** `POST /api/marketing/leads`  
**Permission:** `marketing.projects.view`

**Request:**
```json
{
  "name": "محمد أحمد",
  "contact_info": "0501234567",
  "source": "Facebook",
  "status": "new",
  "project_id": 5,
  "assigned_to": 8
}
```

**Response:**
```json
{
  "success": true,
  "message": "Lead created successfully",
  "data": {
    "id": 1,
    "name": "محمد أحمد",
    "status": "new"
  }
}
```

### Update Lead
**Endpoint:** `PUT /api/marketing/leads/{leadId}`  
**Permission:** `marketing.projects.view`

**Request:**
```json
{
  "status": "contacted",
  "assigned_to": 10
}
```

**Response:**
```json
{
  "success": true,
  "message": "Lead updated successfully",
  "data": {
    "id": 1,
    "status": "contacted"
  }
}
```

---

## Reports

### Project Performance Report
**Endpoint:** `GET /api/marketing/reports/project/{projectId}`  
**Permission:** `marketing.reports.view`

**Response:**
```json
{
  "success": true,
  "data": {
    "project_id": 1,
    "total_budget": 100000,
    "spent_budget": 65000,
    "leads_generated": 450,
    "conversions": 32,
    "conversion_rate": 7.11,
    "cost_per_lead": 144.44,
    "cost_per_conversion": 2031.25,
    "roi": 185.5
  }
}
```

### Budget Report
**Endpoint:** `GET /api/marketing/reports/budget`  
**Permission:** `marketing.reports.view`

**Response:**
```json
{
  "success": true,
  "data": {
    "total_allocated": 500000,
    "total_spent": 320000,
    "remaining": 180000,
    "utilization_rate": 64,
    "by_project": [
      {
        "project_id": 1,
        "project_name": "مشروع برج الراكز",
        "allocated": 100000,
        "spent": 65000
      }
    ]
  }
}
```

### Expected Bookings Report
**Endpoint:** `GET /api/marketing/reports/expected-bookings`  
**Permission:** `marketing.reports.view`

**Response:**
```json
{
  "success": true,
  "data": {
    "total_expected": 1500,
    "by_project": [
      {
        "project_id": 1,
        "project_name": "مشروع برج الراكز",
        "expected_bookings": 1087
      }
    ]
  }
}
```

### Employee Performance Report
**Endpoint:** `GET /api/marketing/reports/employee/{userId}`  
**Permission:** `marketing.reports.view`

**Response:**
```json
{
  "success": true,
  "data": {
    "user_id": 5,
    "name": "أحمد محمد",
    "total_leads": 120,
    "conversions": 15,
    "conversion_rate": 12.5,
    "tasks_completed": 45,
    "task_completion_rate": 90,
    "projects_assigned": 3
  }
}
```

### Export Plan
**Endpoint:** `GET /api/marketing/reports/export/{planId}`  
**Permission:** `marketing.reports.view`

**Response:**
```json
{
  "success": true,
  "data": {
    "file_url": "https://example.com/exports/plan_1_20260201.pdf",
    "file_name": "marketing_plan_1.pdf"
  }
}
```

---

## Settings

### List Settings
**Endpoint:** `GET /api/marketing/settings`  
**Permission:** `marketing.budgets.manage`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "key": "conversion_rate",
      "value": "2.5",
      "description": "Default conversion rate for marketing"
    },
    {
      "key": "default_cpm",
      "value": "15.50",
      "description": "Default CPM for campaigns"
    }
  ]
}
```

### Update Setting
**Endpoint:** `PUT /api/marketing/settings/{key}`  
**Permission:** `marketing.budgets.manage`

**Request:**
```json
{
  "value": "3.0",
  "description": "Updated conversion rate"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Setting 'conversion_rate' updated successfully",
  "data": {
    "key": "conversion_rate",
    "value": "3.0",
    "description": "Updated conversion rate"
  }
}
```

---

## Error Responses

All endpoints may return the following error responses:

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "error": "Unauthorized. Marketing permission required."
}
```

### 404 Not Found
```json
{
  "message": "Resource not found"
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "The field is required."
    ]
  }
}
```

### 500 Server Error
```json
{
  "message": "Server Error",
  "error": "Error details"
}
```

---

## Notes

- All dates are in ISO 8601 format (YYYY-MM-DDTHH:MM:SSZ)
- All monetary values are in SAR (Saudi Riyal)
- Pagination is available on list endpoints using `per_page` and `page` query parameters
- All endpoints require authentication via Sanctum token in the `Authorization: Bearer {token}` header
