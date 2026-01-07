<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إشعارات الموظفين - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #1a1a2e;
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 30px; color: #00d9ff; }

        /* Login Form */
        .login-box {
            background: #16213e;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .login-box h2 { margin-bottom: 20px; color: #00d9ff; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 5px;
            background: #0f0f23;
            color: #fff;
            font-size: 16px;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        .btn-primary { background: #00d9ff; color: #000; }
        .btn-danger { background: #ff4757; color: #fff; }

        /* Status */
        .status {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        .status.connected { background: #00b894; }
        .status.disconnected { background: #d63031; }
        .status.connecting { background: #fdcb6e; color: #000; }

        /* Notifications */
        .notifications-box {
            background: #16213e;
            padding: 20px;
            border-radius: 10px;
            min-height: 300px;
        }
        .notification {
            background: #0f3460;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-right: 4px solid #00d9ff;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .notification .id { color: #00d9ff; font-weight: bold; font-size: 18px; }
        .notification .message { color: #ddd; margin-top: 5px; }
        .notification .time { color: #888; font-size: 12px; margin-top: 5px; }

        .empty { text-align: center; color: #666; padding: 50px; }
        .user-info { background: #0f3460; padding: 10px 15px; border-radius: 5px; margin-bottom: 15px; }

        #error { background: #d63031; padding: 10px; border-radius: 5px; margin-bottom: 15px; display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔔 إشعارات الموظفين الجدد</h1>

        <div id="error"></div>

        <!-- Login Section -->
        <div id="loginSection" class="login-box">
            <h2>تسجيل الدخول (مدير فقط)</h2>
            <div class="form-group">
                <label>البريد الإلكتروني</label>
                <input type="email" id="email" value="admin@gmail.com">
            </div>
            <div class="form-group">
                <label>كلمة المرور</label>
                <input type="password" id="password" value="password">
            </div>
            <button class="btn btn-primary" onclick="login()">دخول</button>
        </div>

        <!-- Connected Section -->
        <div id="connectedSection" style="display: none;">
            <div class="user-info">
                👤 مرحباً: <strong id="userName"></strong>
                <button class="btn btn-danger" onclick="logout()" style="float: left; padding: 5px 15px;">خروج</button>
            </div>

            <div id="status" class="status disconnected">غير متصل</div>

            <div class="notifications-box">
                <h3 style="margin-bottom: 15px;">📥 الإشعارات الواردة:</h3>
                <div id="notifications">
                    <div class="empty">لا توجد إشعارات - انتظر إضافة موظف جديد</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Load Echo -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        let authToken = localStorage.getItem('admin_token');
        let echo = null;

        // Show error
        function showError(msg) {
            const el = document.getElementById('error');
            el.textContent = msg;
            el.style.display = 'block';
            setTimeout(() => el.style.display = 'none', 5000);
        }

        // Update status
        function setStatus(status, text) {
            const el = document.getElementById('status');
            el.className = 'status ' + status;
            el.textContent = text;
        }

        // Add notification to list
        function addNotification(data) {
            const container = document.getElementById('notifications');
            const empty = container.querySelector('.empty');
            if (empty) empty.remove();

            const div = document.createElement('div');
            div.className = 'notification';
            div.innerHTML = `
                <div class="id">موظف جديد #${data.employee_id}</div>
                <div class="message">${data.message}</div>
                <div class="time">${new Date().toLocaleTimeString('ar-SA')}</div>
            `;
            container.insertBefore(div, container.firstChild);
        }

        // Login
        async function login() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            try {
                const res = await fetch('/api/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ email, password })
                });

                const data = await res.json();

                if (!res.ok) {
                    showError(data.message || 'فشل تسجيل الدخول');
                    return;
                }

                authToken = data.token || data.access_token;
                localStorage.setItem('admin_token', authToken);

                document.getElementById('userName').textContent = data.user?.name || email;
                document.getElementById('loginSection').style.display = 'none';
                document.getElementById('connectedSection').style.display = 'block';

                connectWebSocket();

            } catch (e) {
                showError('خطأ في الاتصال');
            }
        }

        // Logout
        function logout() {
            localStorage.removeItem('admin_token');
            authToken = null;
            if (echo) {
                echo.disconnect();
                echo = null;
            }
            document.getElementById('loginSection').style.display = 'block';
            document.getElementById('connectedSection').style.display = 'none';
            document.getElementById('notifications').innerHTML = '<div class="empty">لا توجد إشعارات</div>';
        }

        // Connect to WebSocket
        function connectWebSocket() {
            setStatus('connecting', 'جاري الاتصال...');

            // Create Pusher instance with auth
            echo = new Pusher('{{ env("REVERB_APP_KEY") }}', {
                wsHost: '{{ env("REVERB_HOST", "localhost") }}',
                wsPort: {{ env("REVERB_PORT", 8080) }},
                wssPort: {{ env("REVERB_PORT", 8080) }},
                forceTLS: false,
                enabledTransports: ['ws', 'wss'],
                cluster: 'mt1',
                authEndpoint: '/api/broadcasting/auth',
                auth: {
                    headers: {
                        'Authorization': 'Bearer ' + authToken,
                        'Accept': 'application/json'
                    }
                }
            });

            // Connection events
            echo.connection.bind('connected', () => {
                setStatus('connected', 'متصل ✓');
                console.log('Connected to Reverb');
            });

            echo.connection.bind('disconnected', () => {
                setStatus('disconnected', 'غير متصل');
            });

            echo.connection.bind('error', (err) => {
                console.error('Connection error:', err);
                setStatus('disconnected', 'خطأ في الاتصال');
            });

            // Subscribe to private admin channel
            const channel = echo.subscribe('private-admin-notifications');

            channel.bind('pusher:subscription_succeeded', () => {
                console.log('Subscribed to admin-notifications');
                setStatus('connected', 'متصل ✓ - جاهز للاستقبال');
            });

            channel.bind('pusher:subscription_error', (err) => {
                console.error('Subscription error:', err);
                showError('فشل الاشتراك في القناة - تأكد أنك مدير');
                setStatus('disconnected', 'فشل الاشتراك');
            });

            // Listen for employee created event
            channel.bind('employee.created', (data) => {
                console.log('New employee:', data);
                addNotification(data);
            });
        }

        // Check existing session on page load
        window.onload = function() {
            if (authToken) {
                // Verify token
                fetch('/api/user', {
                    headers: { 'Authorization': 'Bearer ' + authToken, 'Accept': 'application/json' }
                }).then(res => {
                    if (res.ok) {
                        return res.json();
                    }
                    throw new Error('Invalid token');
                }).then(user => {
                    if (user.type !== 'admin') {
                        showError('هذه الصفحة للمدراء فقط');
                        logout();
                        return;
                    }
                    document.getElementById('userName').textContent = user.name;
                    document.getElementById('loginSection').style.display = 'none';
                    document.getElementById('connectedSection').style.display = 'block';
                    connectWebSocket();
                }).catch(() => {
                    localStorage.removeItem('admin_token');
                    authToken = null;
                });
            }
        };
    </script>
</body>
</html>
