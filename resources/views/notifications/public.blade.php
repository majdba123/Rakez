<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Notifications</title>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; min-height: 100vh; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 20px; }
        .status { padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; background: rgba(255,255,255,0.2); }
        .status.connected { background: rgba(46, 204, 113, 0.8); }
        .status.disconnected { background: rgba(231, 76, 60, 0.8); }
        .info { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; }
        .notifications { background: rgba(255,255,255,0.1); border-radius: 12px; padding: 20px; backdrop-filter: blur(10px); }
        .notification { background: rgba(255,255,255,0.2); padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #fff; }
        .notification .time { font-size: 12px; opacity: 0.7; margin-top: 5px; }
        .empty { text-align: center; opacity: 0.7; padding: 40px; }
        .badge { background: #e74c3c; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌐 Public Notifications</h1>

        <div id="status" class="status disconnected">Connecting...</div>

        <div class="info">
            <p>📢 This is a <strong>PUBLIC</strong> channel</p>
            <p>No login required - everyone can see these notifications</p>
        </div>

        <div class="notifications">
            <h3>Notifications <span id="count" class="badge">0</span></h3>
            <div id="notificationsList"></div>
        </div>
    </div>

    <script>
        let pusher = null;
        let channel = null;
        let notifications = [];

        function connectWebSocket() {
            pusher = new Pusher('{{ env("REVERB_APP_KEY") }}', {
                wsHost: '{{ env("REVERB_HOST", "127.0.0.1") }}',
                wsPort: {{ env("REVERB_PORT", 8080) }},
                wssPort: {{ env("REVERB_PORT", 8080) }},
                forceTLS: {{ env("REVERB_SCHEME", "http") === "https" ? 'true' : 'false' }},
                enabledTransports: ['ws', 'wss'],
                cluster: 'mt1'
                // NO auth needed for public channel!
            });

            pusher.connection.bind('connected', () => updateStatus(true));
            pusher.connection.bind('disconnected', () => updateStatus(false));

            // Subscribe to PUBLIC channel (no 'private-' prefix)
            channel = pusher.subscribe('public-notifications');

            // Bind to the event name (without dot prefix for Reverb)
            channel.bind('public.notification', function(data) {
                console.log('Public notification:', data);
                addNotification(data.message);
            });

            channel.bind('pusher:subscription_error', (err) => {
                console.error('Subscription error:', err);
            });
        }

        function updateStatus(connected) {
            const el = document.getElementById('status');
            el.className = 'status ' + (connected ? 'connected' : 'disconnected');
            el.textContent = connected ? '✓ Connected - Listening for public notifications' : '✗ Disconnected';
        }

        function addNotification(message) {
            notifications.unshift({ message, time: new Date().toLocaleTimeString() });
            renderNotifications();
        }

        function renderNotifications() {
            const list = document.getElementById('notificationsList');
            document.getElementById('count').textContent = notifications.length;

            if (notifications.length === 0) {
                list.innerHTML = '<div class="empty">No notifications yet - waiting for broadcasts...</div>';
                return;
            }

            list.innerHTML = notifications.map(n => `
                <div class="notification">
                    <div>${n.message}</div>
                    <div class="time">${n.time}</div>
                </div>
            `).join('');
        }

        // Connect immediately
        connectWebSocket();
        renderNotifications();
    </script>
</body>
</html>

