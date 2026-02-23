<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications</title>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 20px; color: #00d4ff; }
        .status { padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .status.connected { background: #0f5132; color: #75b798; }
        .status.disconnected { background: #842029; color: #f8d7da; }
        .login-form { background: #16213e; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .login-form input { width: 100%; padding: 12px; margin-bottom: 10px; border: none; border-radius: 8px; background: #0f3460; color: #fff; }
        .login-form button { width: 100%; padding: 12px; background: #00d4ff; color: #000; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .notifications { background: #16213e; border-radius: 12px; padding: 20px; }
        .notification { background: #0f3460; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #00d4ff; }
        .notification .time { font-size: 12px; color: #888; margin-top: 5px; }
        .empty { text-align: center; color: #666; padding: 40px; }
        .badge { background: #e94560; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔔 Admin Notifications</h1>

        <div id="status" class="status disconnected">Disconnected</div>

        <div id="loginSection" class="login-form">
            <input type="email" id="email" placeholder="Admin Email" value="admin@example.com">
            <input type="password" id="password" placeholder="Password" value="password">
            <button onclick="login()">Login as Admin</button>
        </div>

        <div id="notificationsSection" style="display:none;">
            <p style="margin-bottom:15px;">Logged in as: <strong id="userName"></strong> <button onclick="logout()" style="margin-left:10px;padding:5px 10px;cursor:pointer;">Logout</button></p>
            <div class="notifications">
                <h3>Notifications <span id="count" class="badge">0</span></h3>
                <div id="notificationsList"></div>
            </div>
        </div>
    </div>

    <script>
        let token = localStorage.getItem('admin_token');
        let pusher = null;
        let channel = null;
        let notifications = [];

        async function login() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            try {
                const res = await fetch('/api/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });
                const data = await res.json();

                if (data.access_token) {
                    token = data.access_token;
                    localStorage.setItem('admin_token', token);
                    document.getElementById('userName').textContent = data.user?.name || email;
                    showNotifications();
                    connectWebSocket();
                } else {
                    alert('Login failed: ' + (data.message || 'Invalid credentials'));
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        }

        function logout() {
            localStorage.removeItem('admin_token');
            token = null;
            if (channel) channel.unbind_all();
            if (pusher) pusher.disconnect();
            document.getElementById('loginSection').style.display = 'block';
            document.getElementById('notificationsSection').style.display = 'none';
            updateStatus(false);
        }

        function showNotifications() {
            document.getElementById('loginSection').style.display = 'none';
            document.getElementById('notificationsSection').style.display = 'block';
        }

        function connectWebSocket() {
            pusher = new Pusher('{{ env("REVERB_APP_KEY") }}', {
                wsHost: '{{ env("REVERB_HOST", "127.0.0.1") }}',
                wsPort: {{ env("REVERB_PORT", 8080) }},
                wssPort: {{ env("REVERB_PORT", 8080) }},
                forceTLS: {{ env("REVERB_SCHEME", "http") === "https" ? 'true' : 'false' }},
                enabledTransports: ['ws', 'wss'],
                cluster: 'mt1',
                authEndpoint: '/api/broadcasting/auth',
                auth: {
                    headers: { 'Authorization': 'Bearer ' + token }
                }
            });

            pusher.connection.bind('connected', () => updateStatus(true));
            pusher.connection.bind('disconnected', () => updateStatus(false));

            // Subscribe to PRIVATE admin channel
            channel = pusher.subscribe('private-admin-notifications');

            // Bind to the event name (without dot prefix for Reverb)
            channel.bind('admin.notification', function(data) {
                console.log('Admin notification:', data);
                addNotification(data.message);
            });

            channel.bind('pusher:subscription_error', (err) => {
                console.error('Subscription error:', err);
            });
        }

        function updateStatus(connected) {
            const el = document.getElementById('status');
            el.className = 'status ' + (connected ? 'connected' : 'disconnected');
            el.textContent = connected ? '✓ Connected to WebSocket' : '✗ Disconnected';
        }

        function addNotification(message) {
            notifications.unshift({ message, time: new Date().toLocaleTimeString() });
            renderNotifications();
        }

        function renderNotifications() {
            const list = document.getElementById('notificationsList');
            document.getElementById('count').textContent = notifications.length;

            if (notifications.length === 0) {
                list.innerHTML = '<div class="empty">No notifications yet</div>';
                return;
            }

            list.innerHTML = notifications.map(n => `
                <div class="notification">
                    <div>${n.message}</div>
                    <div class="time">${n.time}</div>
                </div>
            `).join('');
        }

        // Check existing session
        if (token) {
            showNotifications();
            connectWebSocket();
        }

        renderNotifications();
    </script>
</body>
</html>

