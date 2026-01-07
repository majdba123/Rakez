<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Notifications</title>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #0d1117; color: #c9d1d9; min-height: 100vh; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 20px; color: #58a6ff; }
        .status { padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .status.connected { background: #238636; color: #aff5b4; }
        .status.disconnected { background: #da3633; color: #ffa198; }
        .login-form { background: #161b22; padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #30363d; }
        .login-form input { width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #30363d; border-radius: 8px; background: #0d1117; color: #c9d1d9; }
        .login-form button { width: 100%; padding: 12px; background: #238636; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .notifications { background: #161b22; border-radius: 12px; padding: 20px; border: 1px solid #30363d; }
        .notification { background: #0d1117; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #58a6ff; }
        .notification .time { font-size: 12px; color: #8b949e; margin-top: 5px; }
        .empty { text-align: center; color: #8b949e; padding: 40px; }
        .badge { background: #f85149; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        .user-info { background: #161b22; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #30363d; }
    </style>
</head>
<body>
    <div class="container">
        <h1>👤 User Notifications</h1>

        <div id="status" class="status disconnected">Disconnected</div>

        <div id="loginSection" class="login-form">
            <input type="email" id="email" placeholder="Your Email">
            <input type="password" id="password" placeholder="Password">
            <button onclick="login()">Login</button>
        </div>

        <div id="notificationsSection" style="display:none;">
            <div class="user-info">
                <p>User: <strong id="userName"></strong></p>
                <p>ID: <strong id="userId"></strong></p>
                <button onclick="logout()" style="margin-top:10px;padding:8px 15px;cursor:pointer;background:#da3633;color:#fff;border:none;border-radius:6px;">Logout</button>
            </div>
            <div class="notifications">
                <h3>My Notifications <span id="count" class="badge">0</span></h3>
                <div id="notificationsList"></div>
            </div>
        </div>
    </div>

    <script>
        let token = localStorage.getItem('user_token');
        let userId = localStorage.getItem('user_id');
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
                    userId = data.user?.id;
                    localStorage.setItem('user_token', token);
                    localStorage.setItem('user_id', userId);
                    document.getElementById('userName').textContent = data.user?.name || email;
                    document.getElementById('userId').textContent = userId;
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
            localStorage.removeItem('user_token');
            localStorage.removeItem('user_id');
            token = null;
            userId = null;
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

            // Subscribe to PRIVATE user channel (by user ID)
            console.log('Subscribing to channel: private-user-notifications.' + userId);
            channel = pusher.subscribe('private-user-notifications.' + userId);

            channel.bind('pusher:subscription_succeeded', () => {
                console.log('✅ Successfully subscribed to channel!');
            });

            channel.bind('pusher:subscription_error', (err) => {
                console.error('❌ Subscription error:', err);
            });

            // Bind to the event name (without dot prefix for Reverb)
            channel.bind('user.notification', function(data) {
                console.log('🔔 User notification received:', data);
                const message = data.message || JSON.stringify(data);
                addNotification(message);
            });

            // Debug: listen to all events
            channel.bind_global(function(eventName, data) {
                console.log('📡 Event received:', eventName, data);
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
        if (token && userId) {
            document.getElementById('userId').textContent = userId;
            showNotifications();
            connectWebSocket();
        }

        renderNotifications();
    </script>
</body>
</html>

