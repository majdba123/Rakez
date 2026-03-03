<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>المهام - إدارة المهام</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f5f5; color: #333; min-height: 100vh; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { margin-bottom: 20px; color: #5a4a3a; }
        .login-form { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); max-width: 400px; }
        .login-form input { width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .login-form button { width: 100%; padding: 12px; background: #5a4a3a; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .login-form button:hover { background: #4a3a2a; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #5a4a3a; color: #fff; }
        .btn-primary:hover { background: #4a3a2a; }
        .btn-secondary { background: #e0e0e0; color: #333; }
        .btn-secondary:hover { background: #d0d0d0; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.show { display: flex; }
        .modal { background: #fff; border-radius: 12px; padding: 24px; max-width: 480px; width: 100%; box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
        .modal h2 { margin-bottom: 20px; color: #5a4a3a; font-size: 1.25rem; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }
        .form-group select:disabled { background: #f0f0f0; color: #888; cursor: not-allowed; }
        .form-actions { display: flex; gap: 12px; margin-top: 24px; }
        .form-actions .btn { flex: 1; }
        .tasks-list { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .tasks-list h3 { margin-bottom: 16px; color: #5a4a3a; }
        .empty-state { text-align: center; color: #888; padding: 40px 20px; }
        .user-info { background: #fff; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .user-info p { margin-bottom: 4px; }
        .user-info button { margin-top: 8px; padding: 8px 16px; cursor: pointer; background: #c62828; color: #fff; border: none; border-radius: 6px; }
        .error { color: #c62828; font-size: 0.875rem; margin-top: 4px; }
        .success { color: #2e7d32; font-size: 0.875rem; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>إدارة المهام</h1>

        <div id="loginSection" class="login-form">
            <p style="margin-bottom:12px;">تسجيل الدخول لاستخدام المهام</p>
            <input type="email" id="email" placeholder="البريد الإلكتروني">
            <input type="password" id="password" placeholder="كلمة المرور">
            <button type="button" onclick="login()">دخول</button>
        </div>

        <div id="tasksSection" style="display:none;">
            <div class="user-info">
                <p>مرحباً، <strong id="userName"></strong></p>
                <button type="button" onclick="logout()">تسجيل الخروج</button>
            </div>

            <div class="toolbar">
                <h3>المهام الخاصة بي</h3>
                <button type="button" class="btn btn-primary" onclick="openAddTaskModal()">إضافة مهمة</button>
            </div>

            <div class="tasks-list">
                <div id="tasksListContent" class="empty-state">جاري تحميل المهام...</div>
            </div>
        </div>
    </div>

    <div id="modalOverlay" class="modal-overlay">
        <div class="modal">
            <h2>إضافة مهمة جديدة</h2>
            <form id="addTaskForm" onsubmit="submitTask(event)">
                <div class="form-group">
                    <label for="task_name">اسم المهمة</label>
                    <input type="text" id="task_name" name="task_name" required maxlength="255" placeholder="اسم المهمة">
                </div>
                <div class="form-group">
                    <label for="section">القسم</label>
                    <select id="section" name="section" required>
                        <option value="">اختر القسم...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="due_at">موعد الإستحقاق</label>
                    <input type="datetime-local" id="due_at" name="due_at" required>
                </div>
                <div class="form-group">
                    <label for="assigned_to">المسؤول</label>
                    <select id="assigned_to" name="assigned_to" disabled>
                        <option value="">اختر الموظف المسؤول.....</option>
                    </select>
                    <span id="assigneeHint" class="error" style="display:none;">اختر القسم أولاً</span>
                </div>
                <div id="formError" class="error" style="display:none;"></div>
                <div id="formSuccess" class="success" style="display:none;"></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">حفظ</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddTaskModal()">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const API_BASE = '{{ url("/api") }}';
        let token = localStorage.getItem('user_token');
        let userId = localStorage.getItem('user_id');
        let sections = [];
        let sectionUsers = {};

        function authHeaders() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token,
            };
        }

        async function login() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            try {
                const res = await fetch(API_BASE + '/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ email, password }),
                });
                const data = await res.json();
                if (data.access_token) {
                    token = data.access_token;
                    userId = data.user?.id;
                    localStorage.setItem('user_token', token);
                    localStorage.setItem('user_id', userId);
                    document.getElementById('userName').textContent = data.user?.name || email;
                    document.getElementById('loginSection').style.display = 'none';
                    document.getElementById('tasksSection').style.display = 'block';
                    loadSections();
                    loadMyTasks();
                } else {
                    alert('فشل تسجيل الدخول: ' + (data.message || 'بيانات غير صحيحة'));
                }
            } catch (e) {
                alert('خطأ: ' + e.message);
            }
        }

        function logout() {
            localStorage.removeItem('user_token');
            localStorage.removeItem('user_id');
            token = null;
            userId = null;
            document.getElementById('loginSection').style.display = 'block';
            document.getElementById('tasksSection').style.display = 'none';
        }

        async function loadSections() {
            try {
                const res = await fetch(API_BASE + '/tasks/sections', { headers: authHeaders() });
                const data = await res.json();
                if (data.success && Array.isArray(data.data)) {
                    sections = data.data;
                    const sel = document.getElementById('section');
                    sel.innerHTML = '<option value="">اختر القسم...</option>';
                    data.data.forEach(function (item) {
                        const opt = document.createElement('option');
                        opt.value = item.value;
                        opt.textContent = item.label || item.value;
                        sel.appendChild(opt);
                    });
                }
            } catch (e) {
                console.error('Load sections error', e);
            }
        }

        async function loadUsersBySection(sectionValue) {
            if (!sectionValue) {
                document.getElementById('assigned_to').disabled = true;
                document.getElementById('assigned_to').innerHTML = '<option value="">اختر الموظف المسؤول.....</option>';
                document.getElementById('assigneeHint').style.display = 'inline';
                return;
            }
            document.getElementById('assigneeHint').style.display = 'none';
            if (sectionUsers[sectionValue]) {
                fillAssigneeOptions(sectionUsers[sectionValue]);
                document.getElementById('assigned_to').disabled = false;
                return;
            }
            try {
                const res = await fetch(API_BASE + '/tasks/sections/' + encodeURIComponent(sectionValue) + '/users', { headers: authHeaders() });
                const data = await res.json();
                if (data.success && Array.isArray(data.data)) {
                    sectionUsers[sectionValue] = data.data;
                    fillAssigneeOptions(data.data);
                    document.getElementById('assigned_to').disabled = false;
                }
            } catch (e) {
                console.error('Load users by section error', e);
                document.getElementById('assigned_to').innerHTML = '<option value="">خطأ في تحميل الموظفين</option>';
            }
        }

        function fillAssigneeOptions(users) {
            const sel = document.getElementById('assigned_to');
            sel.innerHTML = '<option value="">اختر الموظف المسؤول.....</option>';
            users.forEach(function (u) {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.name || ('#' + u.id);
                sel.appendChild(opt);
            });
        }

        document.getElementById('section').addEventListener('change', function () {
            const sectionValue = this.value;
            document.getElementById('assigned_to').value = '';
            loadUsersBySection(sectionValue);
        });

        function openAddTaskModal() {
            document.getElementById('addTaskForm').reset();
            document.getElementById('formError').style.display = 'none';
            document.getElementById('formSuccess').style.display = 'none';
            document.getElementById('assigned_to').disabled = true;
            document.getElementById('assigned_to').innerHTML = '<option value="">اختر الموظف المسؤول.....</option>';
            document.getElementById('assigneeHint').style.display = 'inline';
            const sectionVal = document.getElementById('section').value;
            if (sectionVal) loadUsersBySection(sectionVal);
            document.getElementById('modalOverlay').classList.add('show');
        }

        function closeAddTaskModal() {
            document.getElementById('modalOverlay').classList.remove('show');
        }

        async function submitTask(e) {
            e.preventDefault();
            const formError = document.getElementById('formError');
            const formSuccess = document.getElementById('formSuccess');
            formError.style.display = 'none';
            formSuccess.style.display = 'none';

            const taskName = document.getElementById('task_name').value.trim();
            const section = document.getElementById('section').value;
            const assignedTo = document.getElementById('assigned_to').value;
            const dueAtInput = document.getElementById('due_at').value;

            if (!dueAtInput || !dueAtInput.match(/\d{2}:\d{2}/)) {
                formError.textContent = 'يرجى إدخال تاريخ ووقت صحيحين للإستحقاق.';
                formError.style.display = 'block';
                return;
            }

            var dueAt = dueAtInput.replace('T', ' ');
            if (dueAt.length === 16) dueAt += ':00';

            const payload = {
                task_name: taskName,
                section: section,
                assigned_to: parseInt(assignedTo, 10),
                due_at: dueAt,
            };

            try {
                const res = await fetch(API_BASE + '/tasks', {
                    method: 'POST',
                    headers: authHeaders(),
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (res.ok && data.success) {
                    formSuccess.textContent = 'تم إنشاء المهمة بنجاح.';
                    formSuccess.style.display = 'block';
                    loadMyTasks();
                    setTimeout(function () {
                        closeAddTaskModal();
                    }, 800);
                } else {
                    const msg = data.message || (data.errors ? JSON.stringify(data.errors) : 'حدث خطأ');
                    formError.textContent = msg;
                    formError.style.display = 'block';
                }
            } catch (err) {
                formError.textContent = 'خطأ في الاتصال: ' + err.message;
                formError.style.display = 'block';
            }
        }

        async function loadMyTasks() {
            const el = document.getElementById('tasksListContent');
            try {
                const res = await fetch(API_BASE + '/my-tasks', { headers: authHeaders() });
                const data = await res.json();
                if (data.success && Array.isArray(data.data)) {
                    const tasks = data.data;
                    if (tasks.length === 0) {
                        el.innerHTML = 'لا توجد مهام معينة لك.';
                        el.className = 'empty-state';
                        return;
                    }
                    el.className = '';
                    el.innerHTML = '<ul style="list-style:none;">' + tasks.map(function (t) {
                        const due = t.due_at ? new Date(t.due_at).toLocaleString('ar-SA') : '';
                        return '<li style="padding:12px 0; border-bottom:1px solid #eee;"><strong>' + (t.task_name || '—') + '</strong><br><small>الحالة: ' + (t.status || '—') + ' | موعد الإستحقاق: ' + due + '</small></li>';
                    }).join('') + '</ul>';
                } else {
                    el.innerHTML = 'فشل تحميل المهام.';
                    el.className = 'empty-state';
                }
            } catch (e) {
                el.innerHTML = 'خطأ في تحميل المهام.';
                el.className = 'empty-state';
            }
        }

        if (token && userId) {
            document.getElementById('loginSection').style.display = 'none';
            document.getElementById('tasksSection').style.display = 'block';
            document.getElementById('userName').textContent = 'المستخدم';
            loadSections();
            loadMyTasks();
        }
    </script>
</body>
</html>
