import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, test, type Page } from '@playwright/test';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const manifestPath = path.resolve(__dirname, '../../storage/app/e2e-admin-visual-manifest.json');

type RoleKey =
    | 'super_admin'
    | 'erp_admin'
    | 'auditor_readonly'
    | 'credit_admin'
    | 'accounting_admin'
    | 'projects_admin'
    | 'sales_admin'
    | 'hr_admin'
    | 'marketing_admin'
    | 'inventory_admin'
    | 'ai_admin'
    | 'workflow_admin'
    | 'no_panel_user';

type Credential = {
    id: number;
    email: string;
    password: string;
};

type Manifest = {
    base_url: string;
    credentials: Record<RoleKey, Credential>;
    records: Record<string, number>;
};

function readManifest(): Manifest {
    return JSON.parse(fs.readFileSync(manifestPath, 'utf8')) as Manifest;
}

async function waitForAdmin(page: Page): Promise<void> {
    await page.waitForLoadState('domcontentloaded');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(250);
}

async function snapshot(page: Page, name: string): Promise<void> {
    await waitForAdmin(page);
    await expect(page).toHaveScreenshot(name, { fullPage: true });
}

async function login(page: Page, role: RoleKey): Promise<void> {
    const creds = readManifest().credentials[role];

    await page.context().clearCookies();
    await page.goto('/admin/login');
    await waitForAdmin(page);
    await page.reload({ waitUntil: 'networkidle' });
    await waitForAdmin(page);
    await page.locator('input[type="email"]').fill(creds.email);
    await page.locator('input[type="password"]').fill(creds.password);
    await page.getByRole('button', { name: /^sign in$/i }).click();

    if (role === 'no_panel_user') {
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(500);
        return;
    }

    await expect(page).toHaveURL((url) => {
        try {
            const path = new URL(url).pathname.replace(/\/$/, '') || '/';
            return path === '/admin';
        } catch {
            return false;
        }
    }, { timeout: 45_000 });
    await expect(page.locator('body')).toContainText('Overview', { timeout: 15_000 });
    await waitForAdmin(page);
}

async function visit(page: Page, route: string): Promise<void> {
    await page.goto(route);
    await waitForAdmin(page);
}

test.describe.configure({ mode: 'serial' });

test('auth entry, denied access, and admin landing are visually stable', async ({ page }) => {
    await visit(page, '/admin/login');
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await snapshot(page, 'auth--login-page--desktop.png');

    await login(page, 'no_panel_user');
    await expect(page).not.toHaveURL(/\/admin$/);
    await snapshot(page, 'auth--no-panel-denied--desktop.png');

    await login(page, 'erp_admin');
    await expect(page).toHaveURL(/\/admin$/);
    await snapshot(page, 'auth--erp-admin-landing--desktop.png');
});

test('sidebar visibility remains permission-aware for shipped admin roles', async ({ page }) => {
    const sidebarMatrix: Array<{
        role: RoleKey;
        mustSee: string[];
        mustNotSee: string[];
    }> = [
        {
            role: 'super_admin',
            mustSee: [
                'Overview',
                'Access Governance',
                'Governance Observability',
                'Credit Oversight',
                'Accounting & Finance',
                'Contracts & Projects',
                'Sales Oversight',
                'HR Oversight',
                'Marketing Oversight',
                'Inventory Oversight',
                'AI & Knowledge',
                'Requests & Workflow',
            ],
            mustNotSee: [],
        },
        {
            role: 'erp_admin',
            mustSee: [
                'Overview',
                'Access Governance',
                'Governance Observability',
                'Credit Oversight',
                'Accounting & Finance',
                'Contracts & Projects',
                'Sales Oversight',
                'HR Oversight',
                'Marketing Oversight',
                'Inventory Oversight',
                'AI & Knowledge',
                'Requests & Workflow',
            ],
            mustNotSee: [],
        },
        {
            role: 'auditor_readonly',
            mustSee: ['Overview', 'Access Governance', 'Governance Observability'],
            mustNotSee: ['Credit Oversight', 'Accounting & Finance', 'AI & Knowledge', 'Requests & Workflow'],
        },
        {
            role: 'credit_admin',
            mustSee: ['Overview', 'Credit Oversight'],
            mustNotSee: ['Access Governance', 'Accounting & Finance', 'AI & Knowledge'],
        },
        {
            role: 'accounting_admin',
            mustSee: ['Overview', 'Accounting & Finance'],
            mustNotSee: ['Access Governance', 'Credit Oversight', 'AI & Knowledge'],
        },
        {
            role: 'projects_admin',
            mustSee: ['Overview', 'Contracts & Projects'],
            mustNotSee: ['Access Governance', 'Accounting & Finance', 'AI & Knowledge'],
        },
        {
            role: 'sales_admin',
            mustSee: ['Overview', 'Sales Oversight'],
            mustNotSee: ['Access Governance', 'Accounting & Finance', 'AI & Knowledge'],
        },
        {
            role: 'hr_admin',
            mustSee: ['Overview', 'HR Oversight'],
            mustNotSee: ['Access Governance', 'Accounting & Finance', 'AI & Knowledge'],
        },
        {
            role: 'marketing_admin',
            mustSee: ['Overview', 'Marketing Oversight'],
            mustNotSee: ['Access Governance', 'Accounting & Finance', 'AI & Knowledge'],
        },
        {
            role: 'inventory_admin',
            mustSee: ['Overview', 'Inventory Oversight'],
            mustNotSee: ['Access Governance', 'Accounting & Finance', 'AI & Knowledge'],
        },
        {
            role: 'ai_admin',
            mustSee: ['Overview', 'AI & Knowledge'],
            mustNotSee: ['Access Governance', 'Accounting & Finance', 'Credit Oversight'],
        },
        {
            role: 'workflow_admin',
            mustSee: ['Overview', 'Requests & Workflow'],
            mustNotSee: ['Access Governance', 'Accounting & Finance', 'Credit Oversight'],
        },
    ];

    for (const entry of sidebarMatrix) {
        await login(page, entry.role);
        for (const label of entry.mustSee) {
            await expect(page.locator('body')).toContainText(label);
        }
        for (const label of entry.mustNotSee) {
            await expect(page.locator('body')).not.toContainText(label);
        }

        await snapshot(page, `sidebar--${entry.role}--desktop.png`);
        await page.context().clearCookies();
    }
});

test('governance core routes and temporary permissions match Filament access rules', async ({ page }) => {
    const manifest = readManifest();

    await login(page, 'erp_admin');

    const routes = [
        { name: 'governance-home', path: '/admin', text: 'Rakez Governance' },
        { name: 'users-list', path: '/admin/users', text: 'Users' },
        { name: 'users-edit', path: `/admin/users/${manifest.records.review_user_id}/edit`, text: 'Governance Roles' },
        { name: 'roles-list', path: '/admin/roles', text: 'Roles' },
        { name: 'roles-edit', path: `/admin/roles/${manifest.records.workflow_role_id}/edit`, text: 'Permissions' },
        { name: 'permissions-list', path: '/admin/permissions', text: 'Permissions' },
        { name: 'direct-permissions-list', path: '/admin/direct-permissions', text: 'Direct Permissions' },
        { name: 'direct-permissions-edit', path: `/admin/direct-permissions/${manifest.records.review_user_id}/edit`, text: 'Effective Access Snapshot' },
        { name: 'effective-access-list', path: '/admin/effective-access', text: 'Effective Access' },
        { name: 'effective-access-view', path: `/admin/effective-access/${manifest.records.effective_access_user_id}`, text: 'Access Summary' },
        { name: 'governance-audit-list', path: '/admin/governance-audit', text: 'Governance Audit' },
        { name: 'governance-audit-view', path: `/admin/governance-audit/${manifest.records.governance_audit_log_id}`, text: 'Payload' },
    ];

    for (const route of routes) {
        await visit(page, route.path);
        await expect(page.locator('body')).toContainText(route.text);
        await snapshot(page, `core--${route.name}--desktop.png`);
    }

    const createHref = page.locator('a[href*="/governance-temporary-permissions/create"]');

    await visit(page, '/admin/governance-temporary-permissions');
    await expect(page.locator('body')).toContainText('Temporary Permissions');
    await expect(page.locator('body')).toContainText('admin.dashboard.view');
    await expect(page.locator('body')).toContainText('Revoke');
    await expect(createHref).toHaveCount(1);
    await snapshot(page, 'temp-perms--erp-admin-list--desktop.png');

    await visit(page, '/admin/governance-temporary-permissions/create');
    await expect(page.locator('body')).toContainText('User');
    await expect(page.locator('body')).toContainText('Permission');
    await expect(page.locator('body')).toContainText('Expires at');
    await snapshot(page, 'temp-perms--erp-admin-create--desktop.png');

    await page.context().clearCookies();
    await login(page, 'auditor_readonly');
    await visit(page, '/admin/governance-temporary-permissions');
    await expect(page.locator('body')).toContainText('Temporary Permissions');
    await expect(page.locator('body')).toContainText('admin.dashboard.view');
    await expect(createHref).toHaveCount(0);
    await expect(page.locator('body')).not.toContainText('Revoke');
    await snapshot(page, 'temp-perms--auditor-readonly-list--desktop.png');

    await visit(page, '/admin/governance-temporary-permissions/create');
    await expect(page.locator('body')).toContainText('403');
    await snapshot(page, 'temp-perms--auditor-create-forbidden--desktop.png');

    await page.context().clearCookies();
    await login(page, 'workflow_admin');
    await visit(page, '/admin/governance-temporary-permissions');
    await expect(page.locator('body')).toContainText('403');
    await snapshot(page, 'temp-perms--workflow-forbidden--desktop.png');

    await page.setViewportSize({ width: 768, height: 900 });
    await page.context().clearCookies();
    await login(page, 'erp_admin');
    await visit(page, '/admin/governance-temporary-permissions');
    await snapshot(page, 'temp-perms--erp-admin-list--tablet.png');
    await page.setViewportSize({ width: 1600, height: 1200 });
    await page.context().clearCookies();
});

test('live business oversight sections render correctly for shipped routes', async ({ page }) => {
    const manifest = readManifest();

    await login(page, 'erp_admin');

    const routes = [
        { name: 'credit-overview', path: '/admin/credit-overview', text: 'Credit' },
        { name: 'credit-bookings-list', path: '/admin/credit-bookings', text: 'Booking Review' },
        { name: 'credit-bookings-view', path: `/admin/credit-bookings/${manifest.records.credit_booking_id}`, text: 'Transfer and Claim Review' },
        { name: 'title-transfers-list', path: '/admin/title-transfers', text: 'Title Transfer Review' },
        { name: 'title-transfers-view', path: `/admin/title-transfers/${manifest.records.title_transfer_id}`, text: 'Transfer Review' },
        { name: 'claim-files-list', path: '/admin/claim-files', text: 'Claim File Review' },
        { name: 'claim-files-view', path: `/admin/claim-files/${manifest.records.claim_file_id}`, text: 'Snapshot' },
        { name: 'credit-notifications-list', path: '/admin/credit-notifications', text: 'Notification Review' },
        { name: 'accounting-overview', path: '/admin/accounting-overview', text: 'Accounting' },
        { name: 'accounting-deposits-list', path: '/admin/accounting-deposits', text: 'Deposits' },
        { name: 'commission-distributions-list', path: '/admin/commission-distributions', text: 'Commission Distributions' },
        { name: 'salary-distributions-list', path: '/admin/salary-distributions', text: 'Salary Distributions' },
        { name: 'projects-overview', path: '/admin/projects-overview', text: 'Projects' },
        { name: 'contracts-list', path: '/admin/contracts', text: 'Contracts' },
        { name: 'project-media-list', path: '/admin/project-media', text: 'Project Media' },
        { name: 'project-media-view', path: `/admin/project-media/${manifest.records.project_media_id}`, text: 'Project Media' },
        { name: 'exclusive-project-requests-list', path: '/admin/exclusive-project-requests', text: 'Exclusive Project Requests' },
        { name: 'sales-overview', path: '/admin/sales-overview', text: 'Sales' },
        { name: 'sales-reservations-list', path: '/admin/sales-reservations', text: 'Sales Reservations' },
        { name: 'sales-reservations-view', path: `/admin/sales-reservations/${manifest.records.sales_reservation_id}`, text: 'Client' },
        { name: 'hr-overview', path: '/admin/hr-overview', text: 'HR' },
        { name: 'hr-teams-list', path: '/admin/hr-teams', text: 'Teams' },
        { name: 'marketing-overview', path: '/admin/marketing-overview', text: 'Marketing' },
        { name: 'marketing-projects-list', path: '/admin/marketing-projects-admin', text: 'Marketing Projects' },
        { name: 'inventory-overview', path: '/admin/inventory-overview', text: 'Inventory' },
        { name: 'inventory-units-list', path: '/admin/inventory-units', text: 'Inventory Units' },
        { name: 'ai-overview', path: '/admin/ai-overview', text: 'AI Governance Overview' },
        { name: 'knowledge-list', path: '/admin/assistant-knowledge-entries', text: 'Knowledge Review' },
        { name: 'knowledge-view', path: `/admin/assistant-knowledge-entries/${manifest.records.knowledge_entry_id}`, text: 'Content' },
        { name: 'ai-interaction-logs-list', path: '/admin/ai-interaction-logs', text: 'AI Interaction Logs' },
        { name: 'ai-audit-entries-list', path: '/admin/ai-audit-entries', text: 'AI Audit Trail' },
        { name: 'workflow-overview', path: '/admin/workflow-overview', text: 'Workflow' },
        { name: 'approvals-center', path: '/admin/approvals-center', text: 'Approvals Center' },
        { name: 'workflow-tasks-list', path: '/admin/workflow-tasks', text: 'Workflow Tasks' },
        { name: 'admin-notifications-list', path: '/admin/admin-notifications', text: 'Admin Notifications' },
        { name: 'user-notifications-list', path: '/admin/user-notifications', text: 'User Notifications' },
    ];

    for (const route of routes) {
        await visit(page, route.path);
        await expect(page.locator('body')).toContainText(route.text);
        await snapshot(page, `section--${route.name}--desktop.png`);
    }
});

test('read-only, review-only, and approval-only boundaries stay visually correct', async ({ page }) => {
    await login(page, 'credit_admin');

    await visit(page, '/admin/credit-bookings');
    for (const forbidden of [
        'Initialize Financing',
        'Advance Financing',
        'Reject Financing',
        'Initialize Title Transfer',
        'Generate Claim File',
        'Create',
        'Delete',
        'Edit',
    ]) {
        await expect(page.locator('body')).not.toContainText(forbidden);
    }
    await snapshot(page, 'boundaries--credit-bookings-read-only--desktop.png');

    await visit(page, '/admin/title-transfers');
    for (const expected of ['Schedule', 'Clear Schedule', 'Complete']) {
        await expect(page.locator('body')).toContainText(expected);
    }
    await snapshot(page, 'boundaries--title-transfers-managed--desktop.png');

    await visit(page, '/admin/claim-files');
    for (const forbidden of ['Generate PDF', 'Create', 'Delete', 'Edit']) {
        await expect(page.locator('body')).not.toContainText(forbidden);
    }
    await snapshot(page, 'boundaries--claim-files-read-only--desktop.png');

    await page.context().clearCookies();
    await login(page, 'accounting_admin');

    await visit(page, '/admin/accounting-deposits');
    await expect(page.locator('body')).toContainText('Confirm Receipt');
    await snapshot(page, 'boundaries--accounting-deposits-approval--desktop.png');

    await visit(page, '/admin/commission-distributions');
    await expect(page.locator('body')).toContainText('Approve');
    await expect(page.locator('body')).toContainText('Reject');
    await snapshot(page, 'boundaries--commission-distributions-approval--desktop.png');

    await visit(page, '/admin/salary-distributions');
    await expect(page.locator('body')).toContainText('Approve');
    await snapshot(page, 'boundaries--salary-distributions-approval--desktop.png');

    await page.context().clearCookies();
    await login(page, 'erp_admin');

    await visit(page, '/admin/accounting-deposits');
    await expect(page.locator('body')).not.toContainText('Confirm Receipt');
    await snapshot(page, 'boundaries--erp-accounting-deposits-read-only--desktop.png');

    await visit(page, '/admin/commission-distributions');
    await expect(page.locator('body')).not.toContainText('Mark Paid');
    await snapshot(page, 'boundaries--erp-commission-distributions-read-only--desktop.png');

    await visit(page, '/admin/assistant-knowledge-entries');
    for (const forbidden of ['Create', 'Delete', 'Edit']) {
        await expect(page.locator('body')).not.toContainText(forbidden);
    }
    await snapshot(page, 'boundaries--knowledge-review-read-only--desktop.png');

    await page.context().clearCookies();
    await login(page, 'ai_admin');

    await visit(page, '/admin/assistant-knowledge-entries');
    await expect(page.locator('body')).toContainText('Create');
    await snapshot(page, 'boundaries--knowledge-crud--desktop.png');
});

test('responsive shell remains intact across desktop and tablet widths', async ({ page }) => {
    await login(page, 'erp_admin');

    const layouts = [
        { width: 1600, height: 1200, name: 'desktop-wide' },
        { width: 1366, height: 960, name: 'laptop' },
        { width: 1024, height: 900, name: 'narrow-desktop' },
    ];

    for (const layout of layouts) {
        await page.setViewportSize({ width: layout.width, height: layout.height });
        await visit(page, '/admin');
        await snapshot(page, `responsive--home--${layout.name}.png`);

        await visit(page, '/admin/credit-bookings');
        await snapshot(page, `responsive--credit-bookings--${layout.name}.png`);
    }
});

test('empty, no-results, and unauthorized states stay graceful', async ({ page }) => {
    await login(page, 'erp_admin');

    await visit(page, '/admin/users?tableSearch=__no_visual_results__');
    await snapshot(page, 'states--users-no-results--desktop.png');

    await visit(page, '/admin/assistant-knowledge-entries?tableSearch=__no_visual_results__');
    await snapshot(page, 'states--knowledge-no-results--desktop.png');

    await page.context().clearCookies();
    await login(page, 'auditor_readonly');

    await visit(page, '/admin/credit-overview');
    await expect(page.locator('body')).toContainText('403');
    await snapshot(page, 'states--auditor-credit-forbidden--desktop.png');

    await page.context().clearCookies();
    await login(page, 'credit_admin');

    await visit(page, '/admin/accounting-overview');
    await expect(page.locator('body')).toContainText('403');
    await snapshot(page, 'states--credit-accounting-forbidden--desktop.png');
});
