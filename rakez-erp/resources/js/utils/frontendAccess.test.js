import test from 'node:test';
import assert from 'node:assert/strict';
import { buildEmptyState, buildSidebarModel, hasVisibleSections } from './frontendAccess.js';

test('buildSidebarModel renders only visible sections and visible tabs', () => {
    const profile = {
        frontend: {
            sections: {
                sales: {
                    label: 'Sales',
                    visible: true,
                    tabs: {
                        dashboard: { label: 'Dashboard', route: '/sales/dashboard', visible: true },
                        hidden_tab: { label: 'Hidden', route: '/sales/hidden', visible: false },
                    },
                    actions: { create_reservation: true },
                },
                hr: {
                    label: 'HR',
                    visible: false,
                    tabs: {
                        dashboard: { label: 'Dashboard', route: '/hr/dashboard', visible: true },
                    },
                    actions: { manage_employees: true },
                },
            },
        },
    };

    const sidebar = buildSidebarModel(profile);
    assert.equal(sidebar.length, 1);
    assert.equal(sidebar[0].key, 'sales');
    assert.equal(sidebar[0].tabs.length, 1);
    assert.equal(sidebar[0].tabs[0].key, 'dashboard');
});

test('buildSidebarModel is deterministic for the same payload', () => {
    const profile = {
        frontend: {
            sections: {
                notifications: {
                    label: 'Notifications',
                    visible: true,
                    tabs: {
                        inbox: { label: 'Inbox', route: '/notifications', visible: true },
                    },
                    actions: { view_notifications: true },
                },
            },
        },
    };

    const first = buildSidebarModel(profile);
    const second = buildSidebarModel(profile);

    assert.deepEqual(first, second);
});

test('empty fallback is shown when no section is visible', () => {
    const sidebar = buildSidebarModel({
        frontend: { sections: { sales: { visible: false, tabs: {}, actions: {} } } },
    });

    assert.equal(hasVisibleSections(sidebar), false);

    const empty = buildEmptyState(sidebar);
    assert.equal(empty.visible, true);
});
