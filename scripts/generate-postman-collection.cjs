/**
 * Generates the complete Rakez ERP Postman collection from route definitions.
 * Run: node scripts/generate-postman-collection.js
 */
const fs = require('fs');
const path = require('path');

const BASE = '{{base_url}}';

function req(name, method, url, opts = {}) {
  const r = {
    name,
    request: {
      method: method.toUpperCase(),
      header: [
        { key: 'Accept', value: 'application/json', type: 'text' },
        ...((['POST','PUT','PATCH'].includes(method.toUpperCase())) ? [{ key: 'Content-Type', value: 'application/json', type: 'text' }] : []),
      ],
      url: { raw: `${BASE}/${url}`, host: [BASE], path: url.split('/') },
    },
  };

  if (opts.desc) r.request.description = opts.desc;
  if (opts.noAuth) r.request.auth = { type: 'noauth' };
  if (opts.body) {
    r.request.body = { mode: 'raw', raw: JSON.stringify(opts.body, null, 2), options: { raw: { language: 'json' } } };
  }
  if (opts.formdata) {
    r.request.body = { mode: 'formdata', formdata: opts.formdata };
  }
  if (opts.query) {
    r.request.url.query = opts.query.map(q => ({ key: q[0], value: q[1], description: q[2] || '' }));
  }
  if (opts.test) {
    r.event = [{ listen: 'test', script: { type: 'text/javascript', exec: opts.test } }];
  }
  if (opts.preReq) {
    r.event = r.event || [];
    r.event.push({ listen: 'prerequest', script: { type: 'text/javascript', exec: opts.preReq } });
  }
  return r;
}

function folder(name, items, desc) {
  const f = { name, item: items };
  if (desc) f.description = desc;
  return f;
}

function permNote(role, perm) {
  const parts = [];
  if (role) parts.push(`**Role**: ${role}`);
  if (perm) parts.push(`**Permission**: \`${perm}\``);
  return parts.join(' | ');
}

// ────────────────────────────────────────────────────────────────
// BUILD COLLECTION
// ────────────────────────────────────────────────────────────────

const authFolder = folder('01 - Auth', [
  req('Health Check', 'GET', 'health', {
    noAuth: true,
    desc: 'Public health-check endpoint for load balancers.\n\nNo authentication required.',
    test: ['pm.test("Status 200", () => pm.response.to.have.status(200));',
           'pm.test("JSON ok", () => pm.expect(pm.response.json().status).to.eql("ok"));'],
  }),
  req('Login', 'POST', 'login', {
    noAuth: true,
    desc: 'Authenticate with email/password. Returns Sanctum token.\n\nNo authentication required.',
    body: { email: '{{user_email}}', password: '{{user_password}}' },
    test: [
      'if (pm.response.code === 200) {',
      '    const d = pm.response.json();',
      '    pm.environment.set("auth_token", d.data.token || d.token);',
      '    if (d.data && d.data.user) pm.environment.set("user_id", d.data.user.id);',
      '    pm.test("Login successful", () => pm.expect(d.success || d.token).to.be.ok);',
      '}',
    ],
  }),
  req('Logout', 'POST', 'logout', {
    desc: 'Revoke the current Sanctum token.\n\n**Auth**: Bearer Token',
  }),
  req('Current User', 'GET', 'user', {
    desc: 'Returns the currently authenticated user.\n\n**Auth**: Bearer Token',
    test: ['pm.test("Status 200", () => pm.response.to.have.status(200));'],
  }),
], 'Authentication endpoints. Login returns a Sanctum Bearer token used by all other requests.');

// ── Notifications ──
const notifFolder = folder('02 - Notifications', [
  folder('Notifications Proxy', [
    req('List Notifications', 'GET', 'notifications', { desc: 'Role-dispatched notification list.\n\n**Auth**: Bearer Token' }),
    req('Mark Notification Read', 'POST', 'notifications/{{notification_id}}/read', { desc: '**Auth**: Bearer Token' }),
    req('Mark All Read', 'POST', 'notifications/read-all', { desc: '**Auth**: Bearer Token' }),
  ]),
  folder('User Notifications', [
    req('Private Notifications', 'GET', 'user/notifications/private', { desc: '**Auth**: Bearer Token' }),
    req('Public Notifications', 'GET', 'user/notifications/public', { desc: '**Auth**: Bearer Token' }),
    req('Mark All Read', 'PATCH', 'user/notifications/mark-all-read', { desc: '**Auth**: Bearer Token' }),
    req('Mark One Read', 'PATCH', 'user/notifications/{{notification_id}}/read', { desc: '**Auth**: Bearer Token' }),
  ]),
  folder('Admin Notifications', [
    req('Admin Notifications', 'GET', 'admin/notifications', { desc: permNote('admin', 'notifications.view') }),
    req('Send to User', 'POST', 'admin/notifications/send-to-user', {
      desc: permNote('admin', 'notifications.manage'),
      body: { user_id: '{{user_id}}', title: 'إشعار تجريبي', body: 'محتوى الإشعار' },
    }),
    req('Send Public', 'POST', 'admin/notifications/send-public', {
      desc: permNote('admin', 'notifications.manage'),
      body: { title: 'إشعار عام', body: 'محتوى الإشعار العام' },
    }),
    req('User Notifications by Admin', 'GET', 'admin/notifications/user/{{user_id}}', { desc: permNote('admin', 'notifications.manage') }),
    req('All Public Notifications', 'GET', 'admin/notifications/public', { desc: permNote('admin', 'notifications.manage') }),
  ]),
]);

// ── Chat ──
const chatFolder = folder('03 - Chat', [
  req('List Conversations', 'GET', 'chat/conversations', { desc: '**Auth**: Bearer Token' }),
  req('Get/Create Conversation', 'GET', 'chat/conversations/{{target_user_id}}', { desc: '**Auth**: Bearer Token' }),
  req('Get Messages', 'GET', 'chat/conversations/{{conversation_id}}/messages', { desc: '**Auth**: Bearer Token' }),
  req('Send Message', 'POST', 'chat/conversations/{{conversation_id}}/messages', {
    desc: '**Auth**: Bearer Token',
    body: { message: 'مرحبا، كيف حالك؟' },
  }),
  req('Mark Conversation Read', 'PATCH', 'chat/conversations/{{conversation_id}}/read', { desc: '**Auth**: Bearer Token' }),
  req('Delete Message', 'DELETE', 'chat/messages/{{message_id}}', { desc: '**Auth**: Bearer Token' }),
  req('Unread Count', 'GET', 'chat/unread-count', { desc: '**Auth**: Bearer Token' }),
]);

// ── Tasks ──
const taskFolder = folder('04 - Tasks', [
  req('Create Task', 'POST', 'tasks', {
    desc: '**Auth**: Bearer Token',
    body: { task_name: 'مهمة جديدة', team_id: '{{team_id}}', due_at: '2026-03-01', assigned_to: '{{user_id}}', status: 'in_progress' },
  }),
  req('My Tasks', 'GET', 'my-tasks', {
    desc: '**Auth**: Bearer Token',
    query: [['status', 'in_progress', 'Filter: in_progress|completed|could_not_complete']],
  }),
  req('Update Task Status', 'PATCH', 'my-tasks/{{task_id}}/status', {
    desc: '**Auth**: Bearer Token',
    body: { status: 'completed' },
  }),
]);

// ── Teams (Global) ──
const teamsGlobalFolder = folder('05 - Teams (Global)', [
  req('List Teams', 'GET', 'teams/index', { desc: permNote(null, 'projects.view') }),
  req('Show Team', 'GET', 'teams/show/{{team_id}}', { desc: permNote(null, 'projects.view') }),
]);

// ── Contracts ──
const contractsFolder = folder('06 - Contracts', [
  folder('User Contracts', [
    req('List My Contracts', 'GET', 'contracts/index', { desc: '**Auth**: Bearer Token' }),
    req('Create Contract', 'POST', 'contracts/store', {
      desc: '**Auth**: Bearer Token',
      body: {
        project_name: 'مشروع الرياض السكني', developer_name: 'شركة التطوير', developer_number: 'DEV-001',
        city: 'الرياض', district: 'حي النرجس', developer_requiment: 'متطلبات المطور',
        units: [{ type: 'شقة', count: 10, price: 500000 }],
      },
    }),
    req('Show Contract', 'GET', 'contracts/show/{{contract_id}}', { desc: '**Auth**: Bearer Token' }),
    req('Update Contract', 'PUT', 'contracts/update/{{contract_id}}', {
      desc: '**Auth**: Bearer Token',
      body: { project_name: 'مشروع الرياض المحدّث', notes: 'ملاحظات محدّثة' },
    }),
    req('Delete Contract', 'DELETE', 'contracts/{{contract_id}}', { desc: '**Auth**: Bearer Token' }),
    req('Store Contract Info', 'POST', 'contracts/store/info/{{contract_id}}', {
      desc: '**Auth**: Bearer Token',
      body: {
        gregorian_date: '2026-01-15', contract_city: 'الرياض', commission_percent: 2.5,
        second_party_name: 'شركة العقارات', second_party_phone: '0501234567',
        second_party_email: 'info@dev.sa',
      },
    }),
  ]),
  folder('Admin Contracts', [
    req('Admin Contract Index', 'GET', 'contracts/admin-index', { desc: permNote('project_management|admin', 'contracts.view_all') }),
    req('PM Update Status', 'PATCH', 'contracts/update-status/{{contract_id}}', {
      desc: permNote('project_management|admin', 'contracts.approve'),
      body: { status: 'approved' },
    }),
    req('Admin Update Status', 'PATCH', 'admin/contracts/adminUpdateStatus/{{contract_id}}', {
      desc: permNote('admin', 'contracts.approve'),
      body: { status: 'approved' },
    }),
    req('Admin Contract Index (admin prefix)', 'GET', 'admin/contracts/adminIndex', { desc: permNote('admin', 'contracts.view_all') }),
  ]),
  folder('Second Party Data', [
    req('Show Second Party', 'GET', 'second-party-data/show/{{contract_id}}', { desc: permNote('project_management|admin', 'second_party.view') }),
    req('Store Second Party', 'POST', 'second-party-data/store/{{contract_id}}', {
      desc: permNote('project_management|admin', 'second_party.edit'),
      body: { real_estate_papers_url: 'https://example.com/papers.pdf', project_logo_url: 'https://example.com/logo.png' },
    }),
    req('Update Second Party', 'PUT', 'second-party-data/update/{{contract_id}}', {
      desc: permNote('project_management|admin', 'second_party.edit'),
      body: { real_estate_papers_url: 'https://example.com/papers-v2.pdf' },
    }),
    req('All Second Parties', 'GET', 'second-party-data/second-parties', { desc: permNote('project_management|admin', 'second_party.view') }),
    req('Contracts by Email', 'GET', 'second-party-data/contracts-by-email', {
      desc: permNote('project_management|admin', 'second_party.view'),
      query: [['email', 'info@dev.sa', 'Second party email']],
    }),
  ]),
  folder('Contract Units', [
    req('List Units by Contract', 'GET', 'contracts/units/show/{{contract_id}}', { desc: permNote('project_management|admin', 'units.view') }),
    req('Upload CSV', 'POST', 'contracts/units/upload-csv/{{contract_id}}', {
      desc: permNote('project_management|admin', 'units.csv_upload'),
      formdata: [{ key: 'csv_file', type: 'file', src: '', description: 'CSV file (max 10MB)' }],
    }),
    req('Store Unit', 'POST', 'contracts/units/store/{{contract_id}}', {
      desc: permNote('project_management|admin', 'units.edit'),
      body: { unit_type: 'شقة', unit_number: 'A-101', price: 450000, area: '120', status: 'available' },
    }),
    req('Update Unit', 'PUT', 'contracts/units/update/{{unit_id}}', {
      desc: permNote('project_management|admin', 'units.edit'),
      body: { price: 460000, status: 'reserved' },
    }),
    req('Delete Unit', 'DELETE', 'contracts/units/delete/{{unit_id}}', { desc: permNote('project_management|admin', 'units.edit') }),
  ]),
  folder('Departments', [
    folder('Boards', [
      req('Show Boards', 'GET', 'boards-department/show/{{contract_id}}', { desc: permNote('project_management|admin', 'departments.boards.view') }),
      req('Store Boards', 'POST', 'boards-department/store/{{contract_id}}', {
        desc: permNote('project_management|admin', 'departments.boards.edit'),
        body: { has_ads: true },
      }),
      req('Update Boards', 'PUT', 'boards-department/update/{{contract_id}}', {
        desc: permNote('project_management|admin', 'departments.boards.edit'),
        body: { has_ads: false },
      }),
    ]),
    folder('Photography', [
      req('Show Photography', 'GET', 'photography-department/show/{{contract_id}}', { desc: permNote('project_management|admin', 'departments.photography.view') }),
      req('Store Photography', 'POST', 'photography-department/store/{{contract_id}}', {
        desc: permNote('project_management|admin', 'departments.photography.edit'),
        body: { image_url: 'https://example.com/img.jpg', video_url: 'https://example.com/vid.mp4' },
      }),
      req('Update Photography', 'PUT', 'photography-department/update/{{contract_id}}', {
        desc: permNote('project_management|admin', 'departments.photography.edit'),
        body: { image_url: 'https://example.com/img-v2.jpg' },
      }),
      req('Approve Photography', 'PATCH', 'photography-department/approve/{{contract_id}}', { desc: permNote('project_management|admin', 'departments.photography.edit') }),
    ]),
    folder('Montage (Editor)', [
      req('Show Montage', 'GET', 'editor/montage-department/show/{{contract_id}}', { desc: permNote('editor|admin', 'departments.montage.view') }),
      req('Store Montage', 'POST', 'editor/montage-department/store/{{contract_id}}', {
        desc: permNote('editor|admin', 'departments.montage.edit'),
        body: { image_url: 'https://example.com/montage.jpg', video_url: 'https://example.com/montage.mp4' },
      }),
      req('Update Montage', 'PUT', 'editor/montage-department/update/{{contract_id}}', {
        desc: permNote('editor|admin', 'departments.montage.edit'),
        body: { description: 'مونتاج محدّث' },
      }),
    ]),
  ]),
]);

// ── Developers ──
const developersFolder = folder('07 - Developers', [
  req('List Developers', 'GET', 'developers', { desc: permNote('project_management|admin|accounting', null) }),
  req('Show Developer', 'GET', 'developers/{{developer_number}}', { desc: permNote('project_management|admin|accounting', null) }),
]);

// ── Project Management ──
const pmFolder = folder('08 - Project Management', [
  folder('Dashboard', [
    req('PM Dashboard', 'GET', 'project_management/dashboard', { desc: permNote('project_management|admin', 'dashboard.analytics.view') }),
    req('Units Statistics', 'GET', 'project_management/dashboard/units-statistics', { desc: permNote('project_management|admin', 'dashboard.analytics.view') }),
  ]),
  folder('Projects', [
    req('List Projects', 'GET', 'project_management/projects', { desc: permNote('project_management|admin', 'contracts.view_all') }),
  ]),
  folder('Contracts', [
    req('Show Contract', 'GET', 'project_management/contracts/{{contract_id}}', { desc: permNote('project_management|admin', 'contracts.view') }),
    req('Export Contract', 'GET', 'project_management/contracts/{{contract_id}}/export', { desc: permNote('project_management|admin', 'contracts.view') }),
    req('Update Project Link', 'PATCH', 'project_management/contracts/{{contract_id}}/project-link', {
      desc: permNote('project_management|admin', 'contracts.view'),
      body: { project_link: 'https://example.com/project' },
    }),
    req('Update Stage', 'PATCH', 'project_management/contracts/{{contract_id}}/stages/{{stage_number}}', {
      desc: permNote('project_management|admin', 'contracts.view'),
      body: { document_link: 'https://example.com/doc', entry_date: '2026-02-20', mark_complete: true },
    }),
  ]),
  folder('Teams', [
    req('List Teams', 'GET', 'project_management/teams/index', { desc: permNote('project_management|admin', 'projects.view') }),
    req('Create Team', 'POST', 'project_management/teams/store', {
      desc: permNote('project_management|admin', 'projects.team.create'),
      body: { name: 'فريق الرياض', description: 'فريق المنطقة الوسطى' },
    }),
    req('Show Team', 'GET', 'project_management/teams/show/{{team_id}}', { desc: permNote('project_management|admin', 'projects.view') }),
    req('Update Team', 'PUT', 'project_management/teams/update/{{team_id}}', {
      desc: permNote('project_management|admin', 'projects.team.create'),
      body: { name: 'فريق الرياض المحدّث' },
    }),
    req('Delete Team', 'DELETE', 'project_management/teams/delete/{{team_id}}', { desc: permNote('project_management|admin', 'projects.team.create') }),
    req('Teams for Contract', 'GET', 'project_management/teams/index/{{contract_id}}', { desc: permNote('project_management|admin', 'projects.view') }),
    req('Team Contracts', 'GET', 'project_management/teams/contracts/{{team_id}}', { desc: permNote('project_management|admin', 'projects.view') }),
    req('Team Contract Locations', 'GET', 'project_management/teams/contracts/locations/{{team_id}}', { desc: permNote('project_management|admin', 'projects.view') }),
    req('Add Teams to Contract', 'POST', 'project_management/teams/add/{{contract_id}}', {
      desc: permNote('project_management|admin', 'projects.team.allocate'),
      body: { team_ids: [1, 2] },
    }),
    req('Remove Teams from Contract', 'POST', 'project_management/teams/remove/{{contract_id}}', {
      desc: permNote('project_management|admin', 'projects.team.allocate'),
      body: { team_ids: [1] },
    }),
  ]),
]);

// ── Editor ──
const editorFolder = folder('09 - Editor', [
  req('Editor Contracts Index', 'GET', 'editor/contracts/index', { desc: permNote('editor|admin', 'contracts.view_all') }),
  req('Editor Show Contract', 'GET', 'editor/contracts/show/{{contract_id}}', { desc: permNote('editor|admin', 'contracts.view') }),
]);

// ── Inventory ──
const inventoryFolder = folder('10 - Inventory', [
  req('Show Contract', 'GET', 'inventory/contracts/show/{{contract_id}}', { desc: '**Role**: inventory|admin' }),
  req('Admin Index', 'GET', 'inventory/contracts/admin-index', { desc: permNote('inventory|admin', 'contracts.view_all') }),
  req('Agency Overview', 'GET', 'inventory/contracts/agency-overview', { desc: permNote('inventory|admin', 'contracts.view_all') }),
  req('Locations', 'GET', 'inventory/contracts/locations', { desc: permNote('inventory|admin', 'contracts.view_all') }),
  req('Second Party Data', 'GET', 'inventory/second-party-data/show/{{contract_id}}', { desc: permNote('inventory|admin', 'second_party.view') }),
  req('Contract Units', 'GET', 'inventory/contracts/units/show/{{contract_id}}', { desc: permNote('inventory|admin', 'units.view') }),
]);

// ── Sales ──
const salesFolder = folder('11 - Sales', [
  folder('Dashboard & Insights', [
    req('Sales Dashboard', 'GET', 'sales/dashboard', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.dashboard.view'),
      query: [['scope', 'me', 'me|team|all'], ['from', '2026-01-01', 'Date filter'], ['to', '2026-02-28', 'Date filter']],
    }),
    req('Sold Units', 'GET', 'sales/sold-units', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.dashboard.view') }),
    req('Sold Unit Commission Summary', 'GET', 'sales/sold-units/{{unit_id}}/commission-summary', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.dashboard.view') }),
    req('Deposits Management', 'GET', 'sales/deposits/management', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.dashboard.view') }),
    req('Deposits Follow-up', 'GET', 'sales/deposits/follow-up', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.dashboard.view') }),
  ]),
  folder('Projects', [
    req('List Projects', 'GET', 'sales/projects', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.projects.view'),
      query: [['per_page', '15', 'Pagination']],
    }),
    req('Show Project', 'GET', 'sales/projects/{{contract_id}}', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.projects.view') }),
    req('Project Units', 'GET', 'sales/projects/{{contract_id}}/units', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.projects.view'),
      query: [['floor', '', 'Floor filter'], ['status', '', 'available|reserved|sold']],
    }),
    req('Reservation Context', 'GET', 'sales/units/{{unit_id}}/reservation-context', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.reservations.create') }),
    req('My Assignments', 'GET', 'sales/assignments/my', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.projects.view') }),
  ]),
  folder('Reservations', [
    req('Create Reservation', 'POST', 'sales/reservations', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.reservations.create'),
      body: {
        contract_id: '{{contract_id}}', contract_unit_id: '{{unit_id}}', contract_date: '2026-02-20',
        reservation_type: 'confirmed_reservation', client_name: 'أحمد محمد', client_mobile: '0501234567',
        client_nationality: 'سعودي', client_iban: 'SA0380000000608010167519',
        payment_method: 'bank_transfer', down_payment_amount: 50000,
        down_payment_status: 'refundable', purchase_mechanism: 'cash',
      },
    }),
    req('List Reservations', 'GET', 'sales/reservations', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.reservations.view'),
      query: [['mine', '1', 'Only my reservations'], ['status', 'confirmed', 'Filter by status']],
    }),
    req('Show Reservation', 'GET', 'sales/reservations/{{reservation_id}}', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.reservations.view') }),
    req('Confirm Reservation', 'POST', 'sales/reservations/{{reservation_id}}/confirm', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.reservations.confirm') }),
    req('Cancel Reservation', 'POST', 'sales/reservations/{{reservation_id}}/cancel', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.reservations.cancel'),
      body: { cancellation_reason: 'تغيّر رأي العميل' },
    }),
    req('Log Reservation Action', 'POST', 'sales/reservations/{{reservation_id}}/actions', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.reservations.view'),
      body: { action_type: 'lead_acquisition', notes: 'تم التواصل مع العميل' },
    }),
    req('Download Voucher', 'GET', 'sales/reservations/{{reservation_id}}/voucher', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.reservations.view') + '\n\nReturns PDF file.' }),
  ]),
  folder('Targets', [
    req('My Targets', 'GET', 'sales/targets/my', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.targets.view'),
      query: [['status', 'in_progress', 'Filter'], ['from', '2026-01-01', ''], ['to', '2026-02-28', '']],
    }),
    req('List Targets', 'GET', 'sales/targets', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.targets.view') }),
    req('Team Targets', 'GET', 'sales/targets/team', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.team.manage') }),
    req('Show Target', 'GET', 'sales/targets/{{target_id}}', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.targets.view') }),
    req('Create Target', 'POST', 'sales/targets', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.team.manage') + '\n\nSales leader only.',
      body: {
        marketer_id: '{{user_id}}', contract_id: '{{contract_id}}',
        target_type: 'reservation', start_date: '2026-02-01', end_date: '2026-02-28',
      },
    }),
    req('Update Target Status', 'PATCH', 'sales/targets/{{target_id}}', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.targets.update'),
      body: { status: 'completed' },
    }),
  ]),
  folder('Attendance', [
    req('My Attendance', 'GET', 'sales/attendance/my', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.attendance.view'),
      query: [['from', '2026-02-01', ''], ['to', '2026-02-28', '']],
    }),
    req('Team Attendance', 'GET', 'sales/attendance/team', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.team.manage'),
      query: [['user_id', '', 'Filter by user'], ['contract_id', '', 'Filter by contract']],
    }),
    req('Create Schedule', 'POST', 'sales/attendance/schedules', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.team.manage'),
      body: { contract_id: '{{contract_id}}', user_id: '{{user_id}}', schedule_date: '2026-03-01', start_time: '08:00:00', end_time: '17:00:00' },
    }),
    req('Update Schedule', 'PATCH', 'sales/attendance/schedules/{{schedule_id}}', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.team.manage'),
      body: { start_time: '09:00:00', end_time: '18:00:00' },
    }),
    req('Delete Schedule', 'DELETE', 'sales/attendance/schedules/{{schedule_id}}', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.team.manage') }),
  ]),
  folder('Team Management', [
    req('Team Projects', 'GET', 'sales/team/projects', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.team.manage') }),
    req('Team Members', 'GET', 'sales/team/members', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.team.manage') }),
    req('Update Emergency Contacts', 'PATCH', 'sales/projects/{{contract_id}}/emergency-contacts', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.team.manage'),
      body: { emergency_contact_number: '0501234567', security_guard_number: '0509876543' },
    }),
    req('Assign Project (Admin)', 'POST', 'admin/sales/project-assignments', {
      desc: permNote('admin', 'sales.team.manage'),
      body: { contract_id: '{{contract_id}}', user_ids: ['{{user_id}}'] },
    }),
  ]),
  folder('Marketing Tasks (Sales)', [
    req('Task Projects', 'GET', 'sales/tasks/projects', { desc: permNote('sales_leader|admin', 'sales.tasks.manage') }),
    req('Task Project Detail', 'GET', 'sales/tasks/projects/{{contract_id}}', { desc: permNote('sales_leader|admin', 'sales.tasks.manage') }),
    req('List Marketing Tasks', 'GET', 'sales/marketing-tasks', { desc: permNote('sales_leader|admin', 'sales.tasks.manage') }),
    req('Create Marketing Task', 'POST', 'sales/marketing-tasks', {
      desc: permNote('sales_leader|admin', 'sales.tasks.manage'),
      body: { contract_id: '{{contract_id}}', task_name: 'تصميم بروشور', marketer_id: '{{user_id}}' },
    }),
    req('Show Marketing Task', 'GET', 'sales/marketing-tasks/{{task_id}}', { desc: permNote('sales_leader|admin', 'sales.tasks.manage') }),
    req('Update Marketing Task', 'PATCH', 'sales/marketing-tasks/{{task_id}}', {
      desc: permNote('sales_leader|admin', 'sales.tasks.manage'),
      body: { status: 'completed' },
    }),
    req('Delete Marketing Task', 'DELETE', 'sales/marketing-tasks/{{task_id}}', { desc: permNote('sales_leader|admin', 'sales.tasks.manage') }),
  ]),
  folder('Waiting List', [
    req('List Waiting', 'GET', 'sales/waiting-list', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.waiting_list.create') }),
    req('Waiting by Unit', 'GET', 'sales/waiting-list/unit/{{unit_id}}', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.waiting_list.create') }),
    req('Show Waiting Entry', 'GET', 'sales/waiting-list/{{waiting_id}}', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.waiting_list.create') }),
    req('Create Waiting Entry', 'POST', 'sales/waiting-list', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.waiting_list.create'),
      body: { contract_id: '{{contract_id}}', contract_unit_id: '{{unit_id}}', client_name: 'خالد علي', client_mobile: '0551234567', priority: 1 },
    }),
    req('Convert to Reservation', 'POST', 'sales/waiting-list/{{waiting_id}}/convert', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.waiting_list.convert'),
      body: {
        contract_date: '2026-03-01', reservation_type: 'confirmed_reservation',
        client_nationality: 'سعودي', client_iban: 'SA0380000000608010167519',
        payment_method: 'bank_transfer', down_payment_amount: 50000,
        down_payment_status: 'refundable', purchase_mechanism: 'cash',
      },
    }),
    req('Cancel Waiting Entry', 'DELETE', 'sales/waiting-list/{{waiting_id}}', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.waiting_list.create') }),
  ]),
  folder('Negotiations', [
    req('Pending Negotiations', 'GET', 'sales/negotiations/pending', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.negotiation.approve') }),
    req('Approve Negotiation', 'POST', 'sales/negotiations/{{negotiation_id}}/approve', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.negotiation.approve'),
      body: { notes: 'تمت الموافقة' },
    }),
    req('Reject Negotiation', 'POST', 'sales/negotiations/{{negotiation_id}}/reject', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.negotiation.approve'),
      body: { reason: 'السعر غير مقبول' },
    }),
  ]),
  folder('Payment Plans (Sales)', [
    req('Show Payment Plan', 'GET', 'sales/reservations/{{reservation_id}}/payment-plan', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.payment-plan.manage') }),
    req('Create Payment Plan', 'POST', 'sales/reservations/{{reservation_id}}/payment-plan', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.payment-plan.manage'),
      body: { installments: [{ due_date: '2026-04-01', amount: 100000, description: 'الدفعة الأولى' }] },
    }),
    req('Update Installment', 'PUT', 'sales/payment-installments/{{installment_id}}', {
      desc: permNote('sales|sales_leader|admin|project_management', 'sales.payment-plan.manage'),
      body: { amount: 110000, status: 'paid' },
    }),
    req('Delete Installment', 'DELETE', 'sales/payment-installments/{{installment_id}}', { desc: permNote('sales|sales_leader|admin|project_management', 'sales.payment-plan.manage') }),
  ]),
]);

// ── Sales Analytics & Commissions ──
const salesAnalyticsFolder = folder('12 - Sales Analytics & Commissions', [
  folder('Analytics', [
    req('Analytics Dashboard', 'GET', 'sales/analytics/dashboard', { desc: permNote('sales|sales_leader|admin|accounting', 'sales.dashboard.view') }),
    req('Analytics Sold Units', 'GET', 'sales/analytics/sold-units', { desc: permNote('sales|sales_leader|admin|accounting', 'sales.dashboard.view') }),
    req('Deposit Stats by Project', 'GET', 'sales/analytics/deposits/stats/project/{{contract_id}}', { desc: permNote('sales|sales_leader|admin|accounting', 'sales.dashboard.view') }),
    req('Commission Stats by Employee', 'GET', 'sales/analytics/commissions/stats/employee/{{user_id}}', { desc: permNote('sales|sales_leader|admin|accounting', 'sales.dashboard.view') }),
    req('Monthly Commission Report', 'GET', 'sales/analytics/commissions/monthly-report', { desc: permNote('sales|sales_leader|admin|accounting', 'sales.dashboard.view') }),
  ]),
  folder('Commissions', [
    req('List Commissions', 'GET', 'sales/commissions', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Create Commission', 'POST', 'sales/commissions', {
      desc: permNote('sales|sales_leader|admin|accounting', null),
      body: { contract_unit_id: '{{unit_id}}', sales_reservation_id: '{{reservation_id}}', final_selling_price: 500000, commission_percentage: 2.5, commission_source: 'owner' },
    }),
    req('Show Commission', 'GET', 'sales/commissions/{{commission_id}}', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Update Expenses', 'PUT', 'sales/commissions/{{commission_id}}/expenses', {
      desc: permNote('sales|sales_leader|admin|accounting', null),
      body: { marketing_expenses: 5000, bank_fees: 200 },
    }),
    req('Add Distribution', 'POST', 'sales/commissions/{{commission_id}}/distributions', {
      desc: permNote('sales|sales_leader|admin|accounting', null),
      body: { distributions: [{ user_id: '{{user_id}}', type: 'lead_generation', percentage: 30 }] },
    }),
    req('Distribute Lead Generation', 'POST', 'sales/commissions/{{commission_id}}/distribute/lead-generation', { desc: permNote('sales|sales_leader|admin|accounting', null), body: {} }),
    req('Distribute Persuasion', 'POST', 'sales/commissions/{{commission_id}}/distribute/persuasion', { desc: permNote('sales|sales_leader|admin|accounting', null), body: {} }),
    req('Distribute Closing', 'POST', 'sales/commissions/{{commission_id}}/distribute/closing', { desc: permNote('sales|sales_leader|admin|accounting', null), body: {} }),
    req('Distribute Management', 'POST', 'sales/commissions/{{commission_id}}/distribute/management', { desc: permNote('sales|sales_leader|admin|accounting', null), body: {} }),
    req('Approve Commission', 'POST', 'sales/commissions/{{commission_id}}/approve', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Mark Commission Paid', 'POST', 'sales/commissions/{{commission_id}}/mark-paid', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Commission Summary', 'GET', 'sales/commissions/{{commission_id}}/summary', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Update Distribution', 'PUT', 'sales/commissions/distributions/{{distribution_id}}', { desc: permNote('sales|sales_leader|admin|accounting', null), body: { percentage: 35 } }),
    req('Delete Distribution', 'DELETE', 'sales/commissions/distributions/{{distribution_id}}', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Approve Distribution', 'POST', 'sales/commissions/distributions/{{distribution_id}}/approve', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Reject Distribution', 'POST', 'sales/commissions/distributions/{{distribution_id}}/reject', { desc: permNote('sales|sales_leader|admin|accounting', null), body: { notes: 'نسبة غير صحيحة' } }),
  ]),
  folder('Deposits', [
    req('List Deposits', 'GET', 'sales/deposits', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Create Deposit', 'POST', 'sales/deposits', {
      desc: permNote('sales|sales_leader|admin|accounting', null),
      body: { sales_reservation_id: '{{reservation_id}}', contract_id: '{{contract_id}}', contract_unit_id: '{{unit_id}}', amount: 50000, payment_method: 'bank_transfer', client_name: 'أحمد', payment_date: '2026-02-20', commission_source: 'owner' },
    }),
    req('Show Deposit', 'GET', 'sales/deposits/{{deposit_id}}', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Update Deposit', 'PUT', 'sales/deposits/{{deposit_id}}', {
      desc: permNote('sales|sales_leader|admin|accounting', null),
      body: { amount: 55000, notes: 'تم التحديث' },
    }),
    req('Confirm Receipt', 'POST', 'sales/deposits/{{deposit_id}}/confirm-receipt', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Mark Received', 'POST', 'sales/deposits/{{deposit_id}}/mark-received', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Refund Deposit', 'POST', 'sales/deposits/{{deposit_id}}/refund', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Generate Claim File', 'POST', 'sales/deposits/{{deposit_id}}/generate-claim', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Can Refund', 'GET', 'sales/deposits/{{deposit_id}}/can-refund', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Delete Deposit', 'DELETE', 'sales/deposits/{{deposit_id}}', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Bulk Confirm', 'POST', 'sales/deposits/bulk-confirm', {
      desc: permNote('sales|sales_leader|admin|accounting', null),
      body: { deposit_ids: [1, 2, 3] },
    }),
    req('Stats by Project', 'GET', 'sales/deposits/stats/project/{{contract_id}}', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('By Reservation', 'GET', 'sales/deposits/by-reservation/{{reservation_id}}', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Refundable by Project', 'GET', 'sales/deposits/refundable/project/{{contract_id}}', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
    req('Legacy Follow-up', 'GET', 'sales/deposits/legacy-follow-up', { desc: permNote('sales|sales_leader|admin|accounting', null) }),
  ]),
]);

// ── HR ──
const hrFolder = folder('13 - HR', [
  folder('Dashboard', [
    req('HR Dashboard', 'GET', 'hr/dashboard', {
      desc: permNote(null, 'hr.dashboard.view'),
      query: [['year', '2026', ''], ['month', '2', '']],
    }),
    req('Refresh Dashboard', 'POST', 'hr/dashboard/refresh', { desc: permNote(null, 'hr.dashboard.view') }),
  ]),
  folder('Teams', [
    req('List Teams', 'GET', 'hr/teams', { desc: permNote(null, 'hr.teams.manage') }),
    req('Create Team', 'POST', 'hr/teams', {
      desc: permNote(null, 'hr.teams.manage'),
      body: { name: 'فريق جدة', description: 'فريق المنطقة الغربية' },
    }),
    req('Show Team', 'GET', 'hr/teams/{{team_id}}', { desc: permNote(null, 'hr.teams.manage') }),
    req('Update Team', 'PUT', 'hr/teams/{{team_id}}', {
      desc: permNote(null, 'hr.teams.manage'),
      body: { name: 'فريق جدة المحدّث' },
    }),
    req('Delete Team', 'DELETE', 'hr/teams/{{team_id}}', { desc: permNote(null, 'hr.teams.manage') }),
    req('Team Members', 'GET', 'hr/teams/{{team_id}}/members', { desc: permNote(null, 'hr.teams.manage') }),
    req('Assign Member', 'POST', 'hr/teams/{{team_id}}/members', {
      desc: permNote(null, 'hr.teams.manage'),
      body: { user_id: '{{user_id}}' },
    }),
    req('Remove Member', 'DELETE', 'hr/teams/{{team_id}}/members/{{user_id}}', { desc: permNote(null, 'hr.teams.manage') }),
    req('Team Contracts', 'GET', 'hr/teams/contracts/{{team_id}}', { desc: permNote(null, 'hr.teams.manage') }),
    req('Team Contract Locations', 'GET', 'hr/teams/contracts/locations/{{team_id}}', { desc: permNote(null, 'hr.teams.manage') }),
    req('Team Sales Average', 'GET', 'hr/teams/sales-average/{{team_id}}', { desc: permNote(null, 'hr.teams.manage') }),
    req('Teams for Contract', 'GET', 'hr/teams/getTeamsForContract/{{contract_id}}', { desc: permNote(null, 'hr.teams.manage') }),
  ]),
  folder('Performance', [
    req('Marketer Performance Index', 'GET', 'hr/marketers/performance', { desc: permNote(null, 'hr.performance.view') }),
    req('Marketer Performance Detail', 'GET', 'hr/marketers/{{user_id}}/performance', { desc: permNote(null, 'hr.performance.view') }),
  ]),
  folder('Users', [
    req('List Users', 'GET', 'hr/users', {
      desc: permNote(null, 'hr.employees.manage'),
      query: [['is_active', 'true', 'Filter active'], ['type', '', 'sales|hr|admin|...']],
    }),
    req('Create User', 'POST', 'hr/users', {
      desc: permNote(null, 'hr.employees.manage'),
      body: { name: 'موظف جديد', email: 'new@rakez.com', phone: '0501234567', password: 'password123', type: 'sales' },
    }),
    req('List Roles', 'GET', 'hr/users/roles', { desc: permNote(null, 'hr.employees.manage') }),
    req('Show User', 'GET', 'hr/users/{{employee_id}}', { desc: permNote(null, 'hr.employees.manage') }),
    req('Update User', 'PUT', 'hr/users/{{employee_id}}', {
      desc: permNote(null, 'hr.employees.manage'),
      body: { name: 'الاسم المحدّث', phone: '0509876543' },
    }),
    req('Toggle User Status', 'PATCH', 'hr/users/{{employee_id}}/status', { desc: permNote(null, 'hr.employees.manage') }),
    req('Restore User', 'PATCH', 'hr/users/{{employee_id}}/restore', { desc: permNote(null, 'hr.employees.manage') }),
    req('Delete User', 'DELETE', 'hr/users/{{employee_id}}', { desc: permNote(null, 'hr.employees.manage') }),
    req('Upload Files', 'POST', 'hr/users/{{employee_id}}/files', {
      desc: permNote(null, 'hr.employees.manage'),
      formdata: [{ key: 'files[]', type: 'file', src: '', description: 'Employee documents' }],
    }),
  ]),
  folder('Warnings', [
    req('List Warnings', 'GET', 'hr/users/{{employee_id}}/warnings', { desc: permNote(null, 'hr.warnings.manage') }),
    req('Create Warning', 'POST', 'hr/users/{{employee_id}}/warnings', {
      desc: permNote(null, 'hr.warnings.manage'),
      body: { reason: 'تأخر متكرر', type: 'verbal' },
    }),
    req('Delete Warning', 'DELETE', 'hr/warnings/{{warning_id}}', { desc: permNote(null, 'hr.warnings.manage') }),
  ]),
  folder('Employee Contracts', [
    req('List Contracts', 'GET', 'hr/users/{{employee_id}}/contracts', { desc: permNote(null, 'hr.contracts.manage') }),
    req('Create Contract', 'POST', 'hr/users/{{employee_id}}/contracts', {
      desc: permNote(null, 'hr.contracts.manage'),
      body: { start_date: '2026-03-01', end_date: '2027-02-28', salary: 8000, job_title: 'مسوّق' },
    }),
    req('Show Contract', 'GET', 'hr/contracts/{{employee_contract_id}}', { desc: permNote(null, 'hr.contracts.manage') }),
    req('Update Contract', 'PUT', 'hr/contracts/{{employee_contract_id}}', {
      desc: permNote(null, 'hr.contracts.manage'),
      body: { salary: 9000 },
    }),
    req('Generate PDF', 'POST', 'hr/contracts/{{employee_contract_id}}/pdf', { desc: permNote(null, 'hr.contracts.manage') }),
    req('Download PDF', 'GET', 'hr/contracts/{{employee_contract_id}}/pdf', { desc: permNote(null, 'hr.contracts.manage') }),
    req('Activate Contract', 'POST', 'hr/contracts/{{employee_contract_id}}/activate', { desc: permNote(null, 'hr.contracts.manage') }),
    req('Terminate Contract', 'POST', 'hr/contracts/{{employee_contract_id}}/terminate', { desc: permNote(null, 'hr.contracts.manage') }),
  ]),
  folder('Reports', [
    req('Team Performance', 'GET', 'hr/reports/team-performance', { desc: permNote(null, 'hr.reports.view') }),
    req('Marketer Performance', 'GET', 'hr/reports/marketer-performance', { desc: permNote(null, 'hr.reports.view') }),
    req('Marketer Performance PDF', 'GET', 'hr/reports/marketer-performance/pdf', { desc: permNote(null, 'hr.reports.view') }),
    req('Employee Count', 'GET', 'hr/reports/employee-count', { desc: permNote(null, 'hr.reports.view') }),
    req('Expiring Contracts', 'GET', 'hr/reports/expiring-contracts', { desc: permNote(null, 'hr.reports.view') }),
    req('Expiring Contracts PDF', 'GET', 'hr/reports/expiring-contracts/pdf', { desc: permNote(null, 'hr.reports.view') }),
    req('Ended Contracts', 'GET', 'hr/reports/ended-contracts', { desc: permNote(null, 'hr.reports.view') }),
  ]),
]);

// ── Marketing ──
const mktFolder = folder('14 - Marketing', [
  folder('Dashboard', [
    req('Marketing Dashboard', 'GET', 'marketing/dashboard', { desc: permNote('marketing|admin', 'marketing.dashboard.view') }),
  ]),
  folder('Projects', [
    req('List Projects', 'GET', 'marketing/projects', { desc: permNote('marketing|admin', 'marketing.projects.view') }),
    req('Show Project', 'GET', 'marketing/projects/{{contract_id}}', { desc: permNote('marketing|admin', 'marketing.projects.view') }),
    req('Calculate Budget', 'POST', 'marketing/projects/calculate-budget', {
      desc: permNote('marketing|admin', 'marketing.budgets.manage'),
      body: { contract_id: '{{contract_id}}', marketing_value: 100000 },
    }),
    req('Project Team', 'GET', 'marketing/projects/{{project_id}}/team', { desc: permNote('marketing|admin', 'marketing.projects.view') }),
    req('Assign Team', 'POST', 'marketing/projects/{{project_id}}/team', {
      desc: permNote('marketing|admin', 'marketing.projects.view'),
      body: { user_ids: ['{{user_id}}'] },
    }),
    req('Recommend Employee', 'GET', 'marketing/projects/{{project_id}}/recommend-employee', { desc: permNote('marketing|admin', 'marketing.projects.view') }),
  ]),
  folder('Developer Plans', [
    req('Show Developer Plan', 'GET', 'marketing/developer-plans/{{contract_id}}', { desc: permNote('marketing|admin', 'marketing.plans.create') }),
    req('Create Developer Plan', 'POST', 'marketing/developer-plans', {
      desc: permNote('marketing|admin', 'marketing.plans.create'),
      body: { contract_id: '{{contract_id}}', marketing_value: 100000, average_cpm: 25, average_cpc: 3, conversion_rate: 10 },
    }),
  ]),
  folder('Employee Plans', [
    req('Plans by Project', 'GET', 'marketing/employee-plans/project/{{project_id}}', { desc: permNote('marketing|admin', 'marketing.plans.create') }),
    req('Show Plan', 'GET', 'marketing/employee-plans/{{plan_id}}', { desc: permNote('marketing|admin', 'marketing.plans.create') }),
    req('Create Plan', 'POST', 'marketing/employee-plans', {
      desc: permNote('marketing|admin', 'marketing.plans.create'),
      body: { marketing_project_id: '{{project_id}}', user_id: '{{user_id}}' },
    }),
    req('Auto Generate', 'POST', 'marketing/employee-plans/auto-generate', { desc: permNote('marketing|admin', 'marketing.plans.create') }),
  ]),
  folder('Expected Sales', [
    req('Calculate Expected', 'GET', 'marketing/expected-sales/{{project_id}}', { desc: permNote('marketing|admin', 'marketing.budgets.manage') }),
    req('List Expected Sales', 'GET', 'marketing/expected-sales', { desc: permNote('marketing|admin', 'marketing.budgets.manage') }),
    req('Store Expected Sales', 'POST', 'marketing/expected-sales', {
      desc: permNote('marketing|admin', 'marketing.budgets.manage'),
      body: { marketing_value: 100000, average_cpm: 25, conversion_rate: 10 },
    }),
    req('Update Conversion Rate', 'PUT', 'marketing/settings/conversion-rate', {
      desc: permNote('marketing|admin', 'marketing.budgets.manage'),
      body: { value: '12', description: 'معدل التحويل المحدّث' },
    }),
  ]),
  folder('Budget Distributions', [
    req('Create Distribution', 'POST', 'marketing/budget-distributions', {
      desc: permNote('marketing|admin', 'marketing.budgets.manage'),
      body: {
        marketing_project_id: '{{project_id}}', plan_type: 'developer',
        total_budget: 100000, conversion_rate: 10, average_booking_value: 500000,
        platform_distribution: {}, platform_objectives: {}, platform_costs: {},
      },
    }),
    req('Show Distribution', 'GET', 'marketing/budget-distributions/{{project_id}}', { desc: permNote('marketing|admin', 'marketing.budgets.manage') }),
    req('Recalculate', 'POST', 'marketing/budget-distributions/{{distribution_id}}/calculate', { desc: permNote('marketing|admin', 'marketing.budgets.manage') }),
    req('Results', 'GET', 'marketing/budget-distributions/{{distribution_id}}/results', { desc: permNote('marketing|admin', 'marketing.budgets.manage') }),
  ]),
  folder('Tasks', [
    req('List Tasks', 'GET', 'marketing/tasks', {
      desc: permNote('marketing|admin', 'marketing.tasks.view'),
      query: [['status', 'new', 'new|in_progress|completed']],
    }),
    req('Create Task', 'POST', 'marketing/tasks', {
      desc: permNote('marketing|admin', 'marketing.tasks.confirm'),
      body: { contract_id: '{{contract_id}}', task_name: 'تصميم إعلان', marketer_id: '{{user_id}}' },
    }),
    req('Update Task', 'PUT', 'marketing/tasks/{{task_id}}', {
      desc: permNote('marketing|admin', 'marketing.tasks.confirm'),
      body: { task_name: 'تصميم إعلان محدّث' },
    }),
    req('Update Task Status', 'PATCH', 'marketing/tasks/{{task_id}}/status', {
      desc: permNote('marketing|admin', 'marketing.tasks.confirm'),
      body: { status: 'completed' },
    }),
  ]),
  folder('Teams', [
    req('List Marketing Teams', 'GET', 'marketing/teams', { desc: permNote('marketing|admin', 'marketing.teams.view') }),
    req('Assign Campaign', 'POST', 'marketing/teams/assign', {
      desc: permNote('marketing|admin', 'marketing.teams.manage'),
      body: { team_id: '{{team_id}}', campaign_id: 1 },
    }),
  ]),
  folder('Leads', [
    req('List Leads', 'GET', 'marketing/leads', { desc: permNote('marketing|admin', 'marketing.projects.view') }),
    req('Create Lead', 'POST', 'marketing/leads', {
      desc: permNote('marketing|admin', 'marketing.projects.view'),
      body: { name: 'عميل محتمل', contact_info: '0501234567', project_id: '{{contract_id}}', source: 'إعلان' },
    }),
    req('Update Lead', 'PUT', 'marketing/leads/{{lead_id}}', {
      desc: permNote('marketing|admin', 'marketing.projects.view'),
      body: { status: 'contacted' },
    }),
    req('Convert Lead', 'POST', 'marketing/leads/{{lead_id}}/convert', {
      desc: permNote('marketing|admin', 'marketing.projects.view'),
      body: { notes: 'تم التحويل إلى حجز' },
    }),
    req('Assign Lead', 'POST', 'marketing/leads/{{lead_id}}/assign', {
      desc: permNote('marketing|admin', 'marketing.projects.view'),
      body: { assigned_to: '{{user_id}}' },
    }),
  ]),
  folder('Reports', [
    req('Project Performance', 'GET', 'marketing/reports/project/{{project_id}}', { desc: permNote('marketing|admin', 'marketing.reports.view') }),
    req('Budget Report', 'GET', 'marketing/reports/budget', { desc: permNote('marketing|admin', 'marketing.reports.view') }),
    req('Expected Bookings', 'GET', 'marketing/reports/expected-bookings', { desc: permNote('marketing|admin', 'marketing.reports.view') }),
    req('Employee Performance', 'GET', 'marketing/reports/employee/{{user_id}}', { desc: permNote('marketing|admin', 'marketing.reports.view') }),
    req('Export Plan', 'GET', 'marketing/reports/export/{{plan_id}}', { desc: permNote('marketing|admin', 'marketing.reports.view') }),
    req('Export Developer Plan', 'GET', 'marketing/reports/developer-plan/export/{{contract_id}}', { desc: permNote('marketing|admin', 'marketing.reports.view') }),
  ]),
  folder('Settings', [
    req('List Settings', 'GET', 'marketing/settings', { desc: permNote('marketing|admin', 'marketing.budgets.manage') }),
    req('Update Setting', 'PUT', 'marketing/settings/{{setting_key}}', {
      desc: permNote('marketing|admin', 'marketing.budgets.manage'),
      body: { value: '100', description: 'تحديث الإعداد' },
    }),
  ]),
]);

// ── Credit ──
const creditFolder = folder('15 - Credit & Financing', [
  folder('Dashboard', [
    req('Credit Dashboard', 'GET', 'credit/dashboard', { desc: permNote('credit|admin', 'credit.dashboard.view') }),
    req('Refresh Dashboard', 'POST', 'credit/dashboard/refresh', { desc: permNote('credit|admin', 'credit.dashboard.view') }),
  ]),
  folder('Notifications', [
    req('List Notifications', 'GET', 'credit/notifications', { desc: permNote('credit|admin', 'credit.bookings.view') }),
    req('Mark Read', 'POST', 'credit/notifications/{{notification_id}}/read', { desc: permNote('credit|admin', 'credit.bookings.view') }),
    req('Mark All Read', 'POST', 'credit/notifications/read-all', { desc: permNote('credit|admin', 'credit.bookings.view') }),
  ]),
  folder('Bookings', [
    req('All Bookings', 'GET', 'credit/bookings', { desc: permNote('credit|admin', 'credit.bookings.view') }),
    req('Confirmed Bookings', 'GET', 'credit/bookings/confirmed', {
      desc: permNote('credit|admin', 'credit.bookings.view'),
      query: [['credit_status', 'in_progress', 'Filter']],
    }),
    req('Negotiation Bookings', 'GET', 'credit/bookings/negotiation', { desc: permNote('credit|admin', 'credit.bookings.view') }),
    req('Update Negotiation', 'PATCH', 'credit/bookings/negotiation/{{booking_id}}', {
      desc: permNote('credit|admin', 'credit.bookings.view'),
      body: {},
    }),
    req('Waiting Bookings', 'GET', 'credit/bookings/waiting', { desc: permNote('credit|admin', 'credit.bookings.view') }),
    req('Process Waiting', 'POST', 'credit/bookings/waiting/{{booking_id}}/process', {
      desc: permNote('credit|admin', 'credit.bookings.view'),
      body: {},
    }),
    req('Sold Bookings', 'GET', 'credit/bookings/sold', { desc: permNote('credit|admin', 'credit.bookings.view') }),
    req('Cancelled Bookings', 'GET', 'credit/bookings/cancelled', { desc: permNote('credit|admin', 'credit.bookings.view') }),
    req('Show Booking', 'GET', 'credit/bookings/{{booking_id}}', { desc: permNote('credit|admin', 'credit.bookings.view') }),
    req('Cancel Booking', 'POST', 'credit/bookings/{{booking_id}}/cancel', { desc: permNote('credit|admin', 'credit.bookings.view') }),
  ]),
  folder('Financing', [
    req('Initialize Financing', 'POST', 'credit/bookings/{{booking_id}}/financing', {
      desc: permNote('credit|admin', 'credit.financing.manage'),
    }),
    req('Advance Stage', 'POST', 'credit/bookings/{{booking_id}}/financing/advance', {
      desc: permNote('credit|admin', 'credit.financing.manage'),
    }),
    req('Show Financing', 'GET', 'credit/bookings/{{booking_id}}/financing', { desc: permNote('credit|admin', 'credit.bookings.view') }),
    req('Complete Stage', 'PATCH', 'credit/bookings/{{booking_id}}/financing/stage/{{stage_number}}', {
      desc: permNote('credit|admin', 'credit.financing.manage'),
      body: { bank_name: 'الراجحي', client_salary: 15000, employment_type: 'government' },
    }),
    req('Reject Financing', 'POST', 'credit/bookings/{{booking_id}}/financing/reject', {
      desc: permNote('credit|admin', 'credit.financing.manage'),
      body: { reason: 'رفض البنك' },
    }),
  ]),
  folder('Title Transfer', [
    req('Initialize Transfer', 'POST', 'credit/bookings/{{booking_id}}/title-transfer', { desc: permNote('credit|admin', 'credit.title_transfer.manage') }),
    req('Schedule Transfer', 'PATCH', 'credit/title-transfer/{{transfer_id}}/schedule', {
      desc: permNote('credit|admin', 'credit.title_transfer.manage'),
      body: { scheduled_date: '2026-04-01', notes: 'موعد الإفراغ' },
    }),
    req('Unschedule Transfer', 'PATCH', 'credit/title-transfer/{{transfer_id}}/unschedule', { desc: permNote('credit|admin', 'credit.title_transfer.manage') }),
    req('Complete Transfer', 'POST', 'credit/title-transfer/{{transfer_id}}/complete', { desc: permNote('credit|admin', 'credit.title_transfer.manage') }),
    req('Pending Transfers', 'GET', 'credit/title-transfers/pending', { desc: permNote('credit|admin', 'credit.bookings.view') }),
    req('Sold Projects', 'GET', 'credit/sold-projects', { desc: permNote('credit|admin', 'credit.bookings.view') }),
  ]),
  folder('Claim Files', [
    req('List Claim Files', 'GET', 'credit/claim-files', { desc: permNote('credit|admin', 'credit.claim_files.generate') }),
    req('Candidates', 'GET', 'credit/claim-files/candidates', { desc: permNote('credit|admin', 'credit.claim_files.generate') }),
    req('Generate Bulk', 'POST', 'credit/claim-files/generate-bulk', { desc: permNote('credit|admin', 'credit.claim_files.generate') }),
    req('Generate for Booking', 'POST', 'credit/bookings/{{booking_id}}/claim-file', { desc: permNote('credit|admin', 'credit.claim_files.generate') }),
    req('Show Claim File', 'GET', 'credit/claim-files/{{claim_file_id}}', { desc: permNote('credit|admin', 'credit.claim_files.generate') }),
    req('Generate PDF', 'POST', 'credit/claim-files/{{claim_file_id}}/pdf', { desc: permNote('credit|admin', 'credit.claim_files.generate') }),
    req('Download PDF', 'GET', 'credit/claim-files/{{claim_file_id}}/pdf', { desc: permNote('credit|admin', 'credit.claim_files.generate') }),
  ]),
  folder('Payment Plans (Credit)', [
    req('Show Payment Plan', 'GET', 'credit/bookings/{{booking_id}}/payment-plan', { desc: permNote('credit|admin', 'credit.payment_plan.manage') }),
    req('Create Payment Plan', 'POST', 'credit/bookings/{{booking_id}}/payment-plan', {
      desc: permNote('credit|admin', 'credit.payment_plan.manage'),
      body: { installments: [{ due_date: '2026-04-01', amount: 100000, description: 'الدفعة الأولى' }] },
    }),
    req('Update Installment', 'PUT', 'credit/payment-installments/{{installment_id}}', {
      desc: permNote('credit|admin', 'credit.payment_plan.manage'),
      body: { amount: 110000 },
    }),
    req('Delete Installment', 'DELETE', 'credit/payment-installments/{{installment_id}}', { desc: permNote('credit|admin', 'credit.payment_plan.manage') }),
  ]),
]);

// ── Accounting ──
const accFolder = folder('16 - Accounting', [
  folder('Dashboard', [
    req('Accounting Dashboard', 'GET', 'accounting/dashboard', {
      desc: permNote('accounting|admin', 'accounting.dashboard.view'),
      query: [['from_date', '2026-01-01', ''], ['to_date', '2026-12-31', '']],
    }),
  ]),
  folder('Notifications', [
    req('List Notifications', 'GET', 'accounting/notifications', { desc: permNote('accounting|admin', 'accounting.notifications.view') }),
    req('Mark Read', 'POST', 'accounting/notifications/{{notification_id}}/read', { desc: permNote('accounting|admin', 'accounting.notifications.view') }),
    req('Mark All Read', 'POST', 'accounting/notifications/read-all', { desc: permNote('accounting|admin', 'accounting.notifications.view') }),
  ]),
  folder('Sold Units & Commissions', [
    req('Marketers Dropdown', 'GET', 'accounting/marketers', { desc: permNote('accounting|admin', 'accounting.sold-units.view') }),
    req('List Sold Units', 'GET', 'accounting/sold-units', { desc: permNote('accounting|admin', 'accounting.sold-units.view') }),
    req('Show Sold Unit', 'GET', 'accounting/sold-units/{{unit_id}}', { desc: permNote('accounting|admin', 'accounting.sold-units.view') }),
    req('Create Manual Commission', 'POST', 'accounting/sold-units/{{unit_id}}/commission', {
      desc: permNote('accounting|admin', 'accounting.commissions.create'),
      body: { final_selling_price: 500000, commission_percentage: 2.5, commission_source: 'owner' },
    }),
    req('Update Distributions', 'PUT', 'accounting/commissions/{{commission_id}}/distributions', {
      desc: permNote('accounting|admin', 'accounting.sold-units.manage'),
      body: { distributions: [] },
    }),
    req('Approve Distribution', 'POST', 'accounting/commissions/{{commission_id}}/distributions/{{distribution_id}}/approve', { desc: permNote('accounting|admin', 'accounting.commissions.approve') }),
    req('Reject Distribution', 'POST', 'accounting/commissions/{{commission_id}}/distributions/{{distribution_id}}/reject', {
      desc: permNote('accounting|admin', 'accounting.commissions.approve'),
      body: { notes: 'ملاحظات الرفض' },
    }),
    req('Commission Summary', 'GET', 'accounting/commissions/{{commission_id}}/summary', { desc: permNote('accounting|admin', 'accounting.sold-units.view') }),
    req('Confirm Payment', 'POST', 'accounting/commissions/{{commission_id}}/distributions/{{distribution_id}}/confirm', { desc: permNote('accounting|admin', 'accounting.commissions.approve') }),
  ]),
  folder('Deposits', [
    req('Pending Deposits', 'GET', 'accounting/deposits/pending', { desc: permNote('accounting|admin', 'accounting.deposits.view') }),
    req('Confirm Deposit', 'POST', 'accounting/deposits/{{deposit_id}}/confirm', { desc: permNote('accounting|admin', 'accounting.deposits.manage') }),
    req('Follow-up Deposits', 'GET', 'accounting/deposits/follow-up', { desc: permNote('accounting|admin', 'accounting.deposits.view') }),
    req('Refund Deposit', 'POST', 'accounting/deposits/{{deposit_id}}/refund', { desc: permNote('accounting|admin', 'accounting.deposits.manage') }),
    req('Generate Claim File', 'POST', 'accounting/deposits/claim-file/{{reservation_id}}', { desc: permNote('accounting|admin', 'accounting.deposits.view') }),
  ]),
  folder('Salaries', [
    req('List Salaries', 'GET', 'accounting/salaries', {
      desc: permNote('accounting|admin', 'accounting.salaries.view'),
      query: [['month', '2', ''], ['year', '2026', '']],
    }),
    req('Show Employee Salary', 'GET', 'accounting/salaries/{{user_id}}', {
      desc: permNote('accounting|admin', 'accounting.salaries.view'),
      query: [['month', '2', ''], ['year', '2026', '']],
    }),
    req('Create Distribution', 'POST', 'accounting/salaries/{{user_id}}/distribute', {
      desc: permNote('accounting|admin', 'accounting.salaries.distribute'),
      body: { month: 2, year: 2026 },
    }),
    req('Approve Distribution', 'POST', 'accounting/salaries/distributions/{{distribution_id}}/approve', { desc: permNote('accounting|admin', 'accounting.salaries.distribute') }),
    req('Mark as Paid', 'POST', 'accounting/salaries/distributions/{{distribution_id}}/paid', { desc: permNote('accounting|admin', 'accounting.salaries.distribute') }),
  ]),
  folder('Down Payment Confirmations (Legacy)', [
    req('Pending Confirmations', 'GET', 'accounting/pending-confirmations', { desc: permNote('accounting|admin', 'accounting.down_payment.confirm') }),
    req('Confirm Down Payment', 'POST', 'accounting/confirm/{{reservation_id}}', { desc: permNote('accounting|admin', 'accounting.down_payment.confirm') }),
    req('Confirmation History', 'GET', 'accounting/confirmations/history', { desc: permNote('accounting|admin', 'accounting.down_payment.confirm') }),
  ]),
]);

// ── Exclusive Projects ──
const exclusiveFolder = folder('17 - Exclusive Projects', [
  req('List Projects', 'GET', 'exclusive-projects', { desc: permNote(null, 'exclusive_projects.view') }),
  req('Show Project', 'GET', 'exclusive-projects/{{exclusive_project_id}}', { desc: permNote(null, 'exclusive_projects.view') }),
  req('Create Request', 'POST', 'exclusive-projects', {
    desc: permNote(null, 'exclusive_projects.request'),
    body: {
      project_name: 'مشروع حصري جديد', developer_name: 'شركة البناء', developer_contact: '0501234567',
      location_city: 'الرياض', estimated_units: 50,
      units: [{ unit_type: 'فيلا', count: 20, average_price: 1500000 }],
    },
  }),
  req('Approve Project', 'POST', 'exclusive-projects/{{exclusive_project_id}}/approve', { desc: permNote(null, 'exclusive_projects.approve') }),
  req('Reject Project', 'POST', 'exclusive-projects/{{exclusive_project_id}}/reject', {
    desc: permNote(null, 'exclusive_projects.approve'),
    body: { rejection_reason: 'المشروع لا يستوفي الشروط' },
  }),
  req('Complete Contract', 'PUT', 'exclusive-projects/{{exclusive_project_id}}/contract', {
    desc: permNote(null, 'exclusive_projects.contract.complete'),
    body: { units: [{ type: 'فيلا', count: 20, price: 1500000 }] },
  }),
  req('Export Contract', 'GET', 'exclusive-projects/{{exclusive_project_id}}/export', { desc: permNote(null, 'exclusive_projects.contract.export') }),
]);

// ── AI ──
const aiFolder = folder('18 - AI Assistant', [
  folder('AI V1', [
    req('Ask Question', 'POST', 'ai/ask', {
      desc: permNote(null, 'use-ai-assistant') + '\n\nThrottled. Returns suggestions.',
      body: { question: 'ما هي الوحدات المتاحة في مشروع الرياض؟', section: 'sales' },
    }),
    req('Chat', 'POST', 'ai/chat', {
      desc: permNote(null, 'use-ai-assistant') + '\n\nThrottled. Multi-turn conversation.',
      body: { message: 'مرحبا، أريد مساعدة', session_id: '{{session_id}}' },
    }),
    req('List Conversations', 'GET', 'ai/conversations', {
      desc: permNote(null, 'use-ai-assistant'),
      query: [['per_page', '10', 'Pagination']],
    }),
    req('Delete Session', 'DELETE', 'ai/conversations/{{session_id}}', { desc: permNote(null, 'use-ai-assistant') }),
    req('Available Sections', 'GET', 'ai/sections', { desc: permNote(null, 'use-ai-assistant') }),
  ]),
  folder('AI V2 (Rakiz)', [
    req('V2 Chat', 'POST', 'ai/v2/chat', {
      desc: permNote(null, 'use-ai-assistant') + '\n\nRakiz V2: tool calling, RAG, strict JSON response.\n\nResponse: `answer_markdown`, `confidence`, `sources`, `links`, `suggested_actions`, `follow_up_questions`, `access_notes`',
      body: { message: 'كم عدد الوحدات المباعة هذا الشهر؟', session_id: '{{session_id}}', page_context: { route: '/sales/dashboard' } },
    }),
    req('V2 Search', 'POST', 'ai/v2/search', {
      desc: permNote(null, 'use-ai-assistant'),
      body: { query: 'وحدات متاحة في الرياض' },
    }),
    req('V2 Explain Access', 'POST', 'ai/v2/explain-access', {
      desc: permNote(null, 'use-ai-assistant') + '\n\nExplains why a user can/cannot access a route.',
      body: { route: '/sales/reservations', entity_type: 'reservation', entity_id: 1 },
    }),
  ]),
  folder('Help Assistant (Knowledge-based)', [
    req('Assistant Chat', 'POST', 'ai/assistant/chat', {
      desc: permNote(null, 'use-ai-assistant') + '\n\nKnowledge-based assistant.',
      body: { message: 'كيف أنشئ حجز جديد؟', language: 'ar' },
    }),
    req('List Knowledge', 'GET', 'ai/assistant/knowledge', {
      desc: permNote(null, 'manage-ai-knowledge'),
      query: [['module', 'sales', 'Filter by module'], ['language', 'ar', 'ar|en'], ['is_active', 'true', '']],
    }),
    req('Create Knowledge', 'POST', 'ai/assistant/knowledge', {
      desc: permNote(null, 'manage-ai-knowledge'),
      body: { module: 'sales', title: 'كيفية إنشاء حجز', content_md: '# خطوات إنشاء الحجز\n\n1. اختر المشروع...', language: 'ar', tags: ['حجز', 'مبيعات'], is_active: true, priority: 10 },
    }),
    req('Update Knowledge', 'PUT', 'ai/assistant/knowledge/{{knowledge_id}}', {
      desc: permNote(null, 'manage-ai-knowledge'),
      body: { title: 'عنوان محدّث', is_active: false },
    }),
    req('Delete Knowledge', 'DELETE', 'ai/assistant/knowledge/{{knowledge_id}}', { desc: permNote(null, 'manage-ai-knowledge') }),
  ]),
]);

// ── Storage ──
const storageFolder = folder('19 - Storage', [
  req('Access File', 'GET', 'storage/{{file_path}}', { desc: permNote(null, 'contracts.view') + '\n\nReturns file binary. Replace `file_path` with the relative path.' }),
]);

// ────────────────────────────────────────────────────────────────
// ASSEMBLE COLLECTION
// ────────────────────────────────────────────────────────────────

function countRequests(items) {
  let count = 0;
  for (const item of items) {
    if (item.request) count++;
    if (item.item) count += countRequests(item.item);
  }
  return count;
}

const allFolders = [
  authFolder, notifFolder, chatFolder, taskFolder, teamsGlobalFolder,
  contractsFolder, developersFolder, pmFolder, editorFolder, inventoryFolder,
  salesFolder, salesAnalyticsFolder, hrFolder, mktFolder,
  creditFolder, accFolder, exclusiveFolder, aiFolder, storageFolder,
];

const totalEndpoints = countRequests(allFolders);

const collection = {
  info: {
    _postman_id: 'rakez-erp-unified-api-collection',
    name: `Rakez ERP - Unified API Collection (${totalEndpoints} Endpoints)`,
    description: [
      `## Rakez ERP - Complete API Collection`,
      ``,
      `**${totalEndpoints} endpoints** across 19 modules, generated from \`routes/api.php\`.`,
      ``,
      `### Auth`,
      `- Method: **Laravel Sanctum** (Bearer token)`,
      `- Login: \`POST /api/login\` → token saved automatically`,
      ``,
      `### Modules`,
      `01-Auth | 02-Notifications | 03-Chat | 04-Tasks | 05-Teams`,
      `06-Contracts | 07-Developers | 08-Project Management | 09-Editor | 10-Inventory`,
      `11-Sales | 12-Sales Analytics & Commissions | 13-HR | 14-Marketing`,
      `15-Credit & Financing | 16-Accounting | 17-Exclusive Projects | 18-AI | 19-Storage`,
      ``,
      `### Quick Start`,
      `1. Import collection + \`Rakez.local.postman_environment.json\``,
      `2. Run **01-Auth > Login** to auto-set \`auth_token\``,
      `3. Test any endpoint`,
      ``,
      `### RBAC`,
      `Each request description lists required **Role** and **Permission**.`,
      ``,
      `Generated: ${new Date().toISOString().split('T')[0]}`,
    ].join('\n'),
    schema: 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
  },
  auth: {
    type: 'bearer',
    bearer: [{ key: 'token', value: '{{auth_token}}', type: 'string' }],
  },
  event: [
    {
      listen: 'prerequest',
      script: {
        type: 'text/javascript',
        exec: [
          "pm.globals.set('timestamp', Date.now());",
        ],
      },
    },
    {
      listen: 'test',
      script: {
        type: 'text/javascript',
        exec: [
          "pm.test('Response time < 5s', function () {",
          "    pm.expect(pm.response.responseTime).to.be.below(5000);",
          "});",
        ],
      },
    },
  ],
  variable: [
    { key: 'base_url', value: 'http://localhost:8000/api' },
  ],
  item: allFolders,
};

// Write output
const outDir = path.join(__dirname, '..', 'docs', 'postman');
fs.mkdirSync(outDir, { recursive: true });
const outPath = path.join(outDir, 'Rakez.postman_collection.json');
fs.writeFileSync(outPath, JSON.stringify(collection, null, 2), 'utf8');
console.log(`✓ Written ${totalEndpoints} endpoints to ${outPath}`);
console.log(`  File size: ${(fs.statSync(outPath).size / 1024).toFixed(1)} KB`);
