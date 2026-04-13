"""One-off generator for Rakez Marketing Postman collection v2.1. Run from repo: python docs/postman/_generate_marketing_collection.py"""
from __future__ import annotations

import json
from pathlib import Path

BASE = "https://rakez.com.sa/api"

TEST_JSON = r"""pm.test("Status 2xx", function () { pm.expect(pm.response.code).to.be.within(200, 299); });
pm.test("Response time < 30s", function () { pm.expect(pm.response.responseTime).to.be.below(30000); });
try { JSON.parse(pm.response.text()); pm.test("Body is JSON", function () { pm.expect(true).to.be.true; }); } catch (e) { pm.test("Body is JSON", function () { pm.expect.fail("Not JSON: " + e); }); }"""

TEST_JSON_SOFT = r"""pm.test("Status 2xx or auth error", function () { pm.expect([200,201,204,401,403,422]).to.include(pm.response.code); });
pm.test("Response time < 30s", function () { pm.expect(pm.response.responseTime).to.be.below(30000); });"""

TEST_LOGIN = r"""pm.test("Response time < 30s", function () { pm.expect(pm.response.responseTime).to.be.below(30000); });
if (pm.response.code === 200) { try { var j = pm.response.json(); if (j.access_token) { pm.collectionVariables.set("token", j.access_token); } } catch (e) {} }"""

TEST_BINARY = r"""pm.test("Status 2xx or PDF error JSON", function () { pm.expect([200,401,403,422,503]).to.include(pm.response.code); });
pm.test("Response time < 60s", function () { pm.expect(pm.response.responseTime).to.be.below(60000); });"""


def req(
    name: str,
    method: str,
    path: str,
    *,
    desc: str = "",
    verification: str = "",
    body: dict | None = None,
    query: list[tuple[str, str]] | None = None,
    test: str = TEST_JSON_SOFT,
    auth_bearer: bool = True,
) -> dict:
    path = path.strip("/")
    raw = "{{baseUrl}}/" + path if path else "{{baseUrl}}"
    url_obj: dict = {"raw": raw, "host": ["{{baseUrl}}"], "path": [p for p in path.split("/") if p]}
    if query:
        url_obj["query"] = [{"key": k, "value": v, "description": ""} for k, v in query]

    r: dict = {
        "name": name,
        "request": {
            "method": method,
            "header": [{"key": "Accept", "value": "application/json", "type": "text"}],
            "url": url_obj,
            "description": f"{desc}\n\n**Verification:** {verification}",
        },
    }
    if auth_bearer:
        r["request"]["auth"] = {
            "type": "bearer",
            "bearer": [{"key": "token", "value": "{{token}}", "type": "string"}],
        }
    if body is not None:
        r["request"]["body"] = {"mode": "raw", "raw": json.dumps(body, ensure_ascii=False, indent=2)}
        r["request"]["header"].append({"key": "Content-Type", "value": "application/json", "type": "text"})
    r["event"] = [{"listen": "test", "script": {"exec": test.split("\n"), "type": "text/javascript"}}]
    return r


def folder(name: str, items: list, desc: str = "") -> dict:
    out = {"name": name, "item": items}
    if desc:
        out["description"] = desc
    return out


def main() -> None:
    collection = {
        "info": {
            "_postman_id": "rakez-marketing-api-v1",
            "name": "Rakez ERP — Marketing API (production base)",
            "description": "Repo-grounded collection for `routes/api.php` marketing + ads groups. Default `baseUrl` is https://rakez.com.sa/api . Use Bearer token from POST /login (Sanctum). **Do not** run mutating requests against production without approval.",
            "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        },
        "variable": [
            {"key": "baseUrl", "value": BASE},
            {"key": "token", "value": ""},
            {"key": "userEmail", "value": ""},
            {"key": "userPassword", "value": ""},
            {"key": "contractId", "value": ""},
            {"key": "projectId", "value": ""},
            {"key": "planId", "value": ""},
            {"key": "employeePlanId", "value": ""},
            {"key": "developerPlanId", "value": ""},
            {"key": "userId", "value": ""},
            {"key": "teamId", "value": ""},
            {"key": "leadId", "value": ""},
            {"key": "taskId", "value": ""},
            {"key": "settingKey", "value": "conversion_rate"},
        ],
        "auth": {
            "type": "bearer",
            "bearer": [{"key": "token", "value": "{{token}}", "type": "string"}],
        },
        "item": [
            folder(
                "Auth",
                [
                    {
                        "name": "Login (Sanctum token)",
                        "request": {
                            "auth": {"type": "noauth"},
                            "method": "POST",
                            "header": [
                                {"key": "Accept", "value": "application/json"},
                                {"key": "Content-Type", "value": "application/json"},
                            ],
                            "body": {
                                "mode": "raw",
                                "raw": json.dumps(
                                    {"email": "{{userEmail}}", "password": "{{userPassword}}"},
                                    indent=2,
                                ),
                            },
                            "url": {
                                "raw": "{{baseUrl}}/login",
                                "host": ["{{baseUrl}}"],
                                "path": ["login"],
                            },
                            "description": "POST /api/login — `LoginController@login`. Body: `email`, `password` (min 8). Response: `access_token`, `user`. **Live check:** apex host returned nginx 404 for `/api/*` in automated probe; SPA uses `api.rakez.com.sa` in CSP — set `baseUrl` in environment if your deployment differs.",
                        },
                        "event": [
                            {"listen": "test", "script": {"exec": TEST_LOGIN.split("\n"), "type": "text/javascript"}}
                        ],
                    },
                    req(
                        "Logout",
                        "POST",
                        "logout",
                        desc="Deletes all Sanctum tokens for current user.",
                        verification="code-verified only (requires valid token)",
                        body=None,
                        test=TEST_JSON_SOFT,
                    ),
                ],
            ),
            folder(
                "Marketing — Dashboard",
                [
                    req(
                        "Dashboard KPIs",
                        "GET",
                        "marketing/dashboard",
                        desc="Marketing dashboard aggregates. Requires `marketing.dashboard.view`.",
                        verification="code-verified only (production `/api` path not reachable from probe)",
                    ),
                ],
            ),
            folder(
                "Marketing — Projects & budget",
                [
                    req(
                        "List marketing projects",
                        "GET",
                        "marketing/projects",
                        desc="Paginated list. Permission: `marketing.projects.view`.",
                        verification="code-verified only",
                    ),
                    req(
                        "Show project by contract",
                        "GET",
                        "marketing/projects/{{contractId}}",
                        desc="Details for one contract's marketing project. Replace `contractId` (contracts.id).",
                        verification="code-verified only",
                    ),
                    req(
                        "Calculate project budget (preview)",
                        "POST",
                        "marketing/projects/calculate-budget",
                        desc="Preview budget calculation. Permission `marketing.budgets.manage`. See `CalculateBudgetRequest`.",
                        verification="code-derived example body — do not run on production without safe contract_id",
                        body={
                            "contract_id": 0,
                            "marketing_percent": 5,
                            "marketing_value": None,
                            "average_cpm": 10,
                            "average_cpc": 2,
                            "conversion_rate": 2.5,
                            "total_unit_price_override": None,
                        },
                        test=TEST_JSON_SOFT,
                    ),
                ],
            ),
            folder(
                "Marketing — Developer plans",
                [
                    req(
                        "Show developer plan",
                        "GET",
                        "marketing/developer-plans/{{contractId}}",
                        desc="Developer marketing plan for contract. `marketing.plans.create`.",
                        verification="code-verified only",
                    ),
                    req(
                        "Download developer plan PDF",
                        "GET",
                        "marketing/developer-plans/{{contractId}}/pdf",
                        desc="PDF stream. May return JSON on error.",
                        verification="code-verified only",
                        test=TEST_BINARY,
                    ),
                    req(
                        "Developer plan PDF data (JSON)",
                        "GET",
                        "marketing/reports/developer-plan/{{contractId}}/pdf-data",
                        desc="JSON for PDF rendering. `marketing.reports.view`.",
                        verification="code-verified only",
                    ),
                    req(
                        "Calculate developer plan budget",
                        "POST",
                        "marketing/developer-plans/calculate-budget",
                        desc="See `StoreDeveloperPlanRequest` / controller. **Mutating** — example from code only.",
                        verification="code-derived example — not executed on production",
                        body={"contract_id": 0},
                        test=TEST_JSON_SOFT,
                    ),
                    req(
                        "Store developer plan",
                        "POST",
                        "marketing/developer-plans",
                        desc="Creates/updates developer plan. **Mutating**.",
                        verification="code-derived example — not executed on production",
                        body={"contract_id": 0},
                        test=TEST_JSON_SOFT,
                    ),
                ],
            ),
            folder(
                "Marketing — Users (HR list)",
                [
                    req(
                        "List users (dropdown)",
                        "GET",
                        "marketing/users",
                        desc="Same data as GET /hr/users — for employee plan dropdowns. `marketing.plans.create`.",
                        verification="code-verified only",
                    ),
                ],
            ),
            folder(
                "Marketing — Employee plans",
                [
                    req(
                        "List employee plans",
                        "GET",
                        "marketing/employee-plans",
                        desc="Optional query `project_id` supported in controller. Route order: list routes before `{planId}`.",
                        verification="code-verified only",
                        query=[("project_id", "{{projectId}}")],
                    ),
                    req(
                        "List employee plans for project",
                        "GET",
                        "marketing/employee-plans/project/{{projectId}}",
                        desc="Filter by marketing project id.",
                        verification="code-verified only",
                    ),
                    req(
                        "Employee plans PDF data",
                        "GET",
                        "marketing/employee-plans/pdf-data",
                        desc="Query params per `EmployeeMarketingPlanController::pdfData` (see controller).",
                        verification="code-verified only",
                    ),
                    req(
                        "Show employee plan",
                        "GET",
                        "marketing/employee-plans/{{planId}}",
                        desc="Single employee marketing plan.",
                        verification="code-verified only",
                    ),
                    req(
                        "Create employee plan",
                        "POST",
                        "marketing/employee-plans",
                        desc="**Mutating**. See `StoreEmployeePlanRequest`.",
                        verification="code-derived example — not executed on production",
                        body={"marketing_project_id": 0, "user_id": 0},
                        test=TEST_JSON_SOFT,
                    ),
                    req(
                        "Auto-generate employee plans",
                        "POST",
                        "marketing/employee-plans/auto-generate",
                        desc="**Mutating** batch operation.",
                        verification="code-derived example — not executed on production",
                        body={"marketing_project_id": 0},
                        test=TEST_JSON_SOFT,
                    ),
                ],
            ),
            folder(
                "Marketing — Expected sales & conversion",
                [
                    req(
                        "Calculate / upsert expected sales for project",
                        "GET",
                        "marketing/expected-sales/{{projectId}}",
                        desc="Query optional: `marketing_value`, `average_cpm`, `average_cpc`, `conversion_rate` (`CalculateExpectedSalesRequest`). Uses `ExpectedSalesService::createOrUpdateExpectedBookings` — **writes** expected bookings.",
                        verification="code-verified only — mutating side effect; do not run blindly on production",
                        query=[("conversion_rate", "2.5")],
                        test=TEST_JSON_SOFT,
                    ),
                    req(
                        "Update global conversion rate setting",
                        "PUT",
                        "marketing/settings/conversion-rate",
                        desc="Body: `{ \"value\": number }` (0–100). **Mutating**.",
                        verification="code-derived — not executed on production",
                        body={"value": 2.5},
                        test=TEST_JSON_SOFT,
                    ),
                ],
            ),
            folder(
                "Marketing — Tasks",
                [
                    req(
                        "List my marketing tasks",
                        "GET",
                        "marketing/tasks",
                        desc="Query: `date`, `status`, pagination. `marketing.tasks.view`.",
                        verification="code-verified only",
                        query=[("date", ""), ("status", "")],
                    ),
                    req(
                        "Create marketing task",
                        "POST",
                        "marketing/tasks",
                        desc="**Mutating**. `StoreMarketingTaskRequest`.",
                        verification="code-derived — not executed on production",
                        body={"title": "Example", "marketing_project_id": 0},
                        test=TEST_JSON_SOFT,
                    ),
                    req(
                        "Update marketing task",
                        "PUT",
                        "marketing/tasks/{{taskId}}",
                        desc="**Mutating**.",
                        verification="code-derived — not executed on production",
                        body={"title": "Updated"},
                        test=TEST_JSON_SOFT,
                    ),
                    req(
                        "Update task status",
                        "PATCH",
                        "marketing/tasks/{{taskId}}/status",
                        desc="Body: `{ \"status\": \"new\"|\"in_progress\"|\"completed\" }`.",
                        verification="code-derived — not executed on production",
                        body={"status": "completed"},
                        test=TEST_JSON_SOFT,
                    ),
                ],
            ),
            folder(
                "Marketing — Project team",
                [
                    req(
                        "Assign team to project",
                        "POST",
                        "marketing/projects/{{projectId}}/team",
                        desc="**Mutating**. See `TeamManagementController`.",
                        verification="code-derived — not executed on production",
                        body={"team_id": 0},
                        test=TEST_JSON_SOFT,
                    ),
                    req(
                        "Get project team",
                        "GET",
                        "marketing/projects/{{projectId}}/team",
                        desc="Current team assignment.",
                        verification="code-verified only",
                    ),
                    req(
                        "Recommend employee",
                        "GET",
                        "marketing/projects/{{projectId}}/recommend-employee",
                        desc="Recommendation endpoint for staffing.",
                        verification="code-verified only",
                    ),
                ],
            ),
            folder(
                "Marketing — Employees (read-only)",
                [
                    req(
                        "List marketing employees",
                        "GET",
                        "marketing/employees",
                        desc="`marketing.teams.view`.",
                        verification="code-verified only",
                    ),
                    req(
                        "Show marketing employee",
                        "GET",
                        "marketing/employees/{{userId}}",
                        desc="Numeric id.",
                        verification="code-verified only",
                    ),
                ],
            ),
            folder(
                "Marketing — Leads",
                [
                    req(
                        "List leads",
                        "GET",
                        "marketing/leads",
                        desc="Paginated. `StoreLeadRequest` / policies.",
                        verification="code-verified only",
                    ),
                    req(
                        "Create lead",
                        "POST",
                        "marketing/leads",
                        desc="**Mutating**. `project_id` is contracts.id per validation.",
                        verification="code-derived — not executed on production",
                        body={
                            "name": "Example",
                            "contact_info": "+966500000000",
                            "project_id": 0,
                            "source": "web",
                            "status": "new",
                        },
                        test=TEST_JSON_SOFT,
                    ),
                    req(
                        "Update lead",
                        "PUT",
                        "marketing/leads/{{leadId}}",
                        desc="**Mutating**.",
                        verification="code-derived — not executed on production",
                        body={"status": "contacted"},
                        test=TEST_JSON_SOFT,
                    ),
                ],
            ),
            folder(
                "Marketing — Reports & exports",
                [
                    req(
                        "Project performance report",
                        "GET",
                        "marketing/reports/project/{{projectId}}",
                        desc="JSON summary.",
                        verification="code-verified only",
                    ),
                    req(
                        "Budget report",
                        "GET",
                        "marketing/reports/budget",
                        desc="Aggregates employee plans + developer distributions.",
                        verification="code-verified only",
                    ),
                    req(
                        "Expected bookings report",
                        "GET",
                        "marketing/reports/expected-bookings",
                        verification="code-verified only",
                    ),
                    req(
                        "Employee performance",
                        "GET",
                        "marketing/reports/employee/{{userId}}",
                        verification="code-verified only",
                    ),
                    req(
                        "Export employee plan (pdf/excel/csv)",
                        "GET",
                        "marketing/reports/export/{{planId}}",
                        desc="Query `format`: pdf (default), excel, csv. Employee plan id.",
                        verification="code-verified only",
                        query=[("format", "pdf")],
                        test=TEST_BINARY,
                    ),
                    req(
                        "Export distribution PDF by project",
                        "GET",
                        "marketing/reports/distribution/project/{{projectId}}",
                        desc="Project-level platform distribution PDF.",
                        verification="code-verified only",
                        test=TEST_BINARY,
                    ),
                    req(
                        "Export distribution PDF by plan",
                        "GET",
                        "marketing/reports/distribution/{{planId}}",
                        desc="Employee marketing plan id.",
                        verification="code-verified only",
                        test=TEST_BINARY,
                    ),
                ],
            ),
            folder(
                "Marketing — Settings",
                [
                    req(
                        "List marketing settings",
                        "GET",
                        "marketing/settings",
                        desc="`marketing.budgets.manage`.",
                        verification="code-verified only",
                    ),
                    req(
                        "Update setting by key",
                        "PUT",
                        "marketing/settings/{{settingKey}}",
                        desc="**Mutating**. Body: `value` (required), `description` optional. See `UpdateMarketingSettingRequest`.",
                        verification="code-derived — not executed on production",
                        body={"value": "0", "description": ""},
                        test=TEST_JSON_SOFT,
                    ),
                ],
            ),
            folder(
                "Ads (role: admin|marketing)",
                [
                    req(
                        "Ads — list accounts",
                        "GET",
                        "ads/accounts",
                        desc="Requires `auth:sanctum`, `role:admin|marketing`, `marketing.ads.view`.",
                        verification="code-verified only",
                    ),
                    req(
                        "Ads — campaigns",
                        "GET",
                        "ads/campaigns",
                        verification="code-verified only",
                    ),
                    req(
                        "Ads — insights",
                        "GET",
                        "ads/insights",
                        verification="code-verified only",
                    ),
                    req(
                        "Ads — leads",
                        "GET",
                        "ads/leads",
                        verification="code-verified only",
                    ),
                    req(
                        "Ads — leads export",
                        "GET",
                        "ads/leads/export",
                        verification="code-verified only",
                    ),
                    req(
                        "Ads — export snapshot",
                        "POST",
                        "ads/leads/export-snap",
                        desc="**Mutating** snapshot/export.",
                        verification="not executed on production",
                        body={},
                        test=TEST_JSON_SOFT,
                    ),
                    req(
                        "Ads — trigger sync",
                        "POST",
                        "ads/sync",
                        desc="**Mutating** `marketing.ads.manage`.",
                        verification="not executed on production",
                        body={},
                        test=TEST_JSON_SOFT,
                    ),
                    req(
                        "Ads — store outcome",
                        "POST",
                        "ads/outcomes",
                        verification="not executed on production",
                        body={},
                        test=TEST_JSON_SOFT,
                    ),
                    req(
                        "Ads — outcome status",
                        "GET",
                        "ads/outcomes/status",
                        verification="code-verified only",
                    ),
                ],
                desc="Prefix `ads` under same `/api` root. Used by marketing for ad platform integration.",
            ),
            folder(
                "Sales API — Marketing tasks (sales leader)",
                [
                    req(
                        "List projects with marketing tasks",
                        "GET",
                        "sales/tasks/projects",
                        desc="`permission:sales.tasks.manage` inside sales leader group.",
                        verification="code-verified only",
                    ),
                    req(
                        "Marketing tasks for contract",
                        "GET",
                        "sales/tasks/projects/{{contractId}}",
                        verification="code-verified only",
                    ),
                    req(
                        "Create marketing task (sales)",
                        "POST",
                        "sales/marketing-tasks",
                        desc="**Mutating** — Sales\\MarketingTaskController.",
                        verification="not executed on production",
                        body={"contract_id": 0},
                        test=TEST_JSON_SOFT,
                    ),
                    req(
                        "Patch marketing task (sales)",
                        "PATCH",
                        "sales/marketing-tasks/{{taskId}}",
                        verification="not executed on production",
                        body={"status": "completed"},
                        test=TEST_JSON_SOFT,
                    ),
                ],
                desc="These routes live under `Route::prefix('sales')` — full path `/api/sales/...`.",
            ),
        ],
    }

    text = json.dumps(collection, ensure_ascii=False, indent=2)
    # Validate before writing (fail fast if not strict JSON)
    json.loads(text)

    docs_dir = Path(__file__).resolve().parent / "collections"
    docs_dir.mkdir(parents=True, exist_ok=True)
    primary = docs_dir / "Rakez-Marketing-API.postman_collection.json"
    primary.write_text(text, encoding="utf-8", newline="\n")
    print("Wrote", primary)

    # Plain .json name at repo postman/ for drag-and-drop import (same content)
    repo_postman = Path(__file__).resolve().parents[2] / "postman" / "Rakez-Marketing-API.json"
    repo_postman.parent.mkdir(parents=True, exist_ok=True)
    repo_postman.write_text(text, encoding="utf-8", newline="\n")
    print("Wrote", repo_postman)


if __name__ == "__main__":
    main()
