<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Notifications — اختبار فوري</title>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 20px; color: #00d4ff; }
        .status { padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .status.connected { background: #0f5132; color: #75b798; }
        .status.disconnected { background: #842029; color: #f8d7da; }
        .user-bar { background: #16213e; padding: 16px; border-radius: 12px; margin-bottom: 20px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .user-bar form { display: inline; }
        .user-bar button { padding: 8px 14px; background: #e94560; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .notifications { background: #16213e; border-radius: 12px; padding: 20px; }
        .notification { background: #0f3460; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-right: 4px solid #00d4ff; }
        .notification .time { font-size: 12px; color: #888; margin-top: 5px; }
        .empty { text-align: center; color: #666; padding: 40px; }
        .badge { background: #e94560; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        .hint { font-size: 13px; color: #8b949e; margin-top: 12px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
        <h1>إشعارات المسؤول — اختبار فوري</h1>

        <div id="status" class="status disconnected">غير متصل</div>

        <div class="user-bar">
            <span>مسجّل كـ: <strong>{{ auth()->user()->name }}</strong></span>
            <form action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit">تسجيل الخروج</button>
            </form>
        </div>

        <div class="notifications">
            <h3>الإشعارات <span id="count" class="badge">0</span></h3>
            <div id="notificationsList"></div>
        </div>

        <p class="hint">
            جرّب: <code style="direction:ltr;display:inline-block;">GET {{ url('/test/broadcast/admin') }}</code>
            (يجب أن تكون مسجّلًا كمسؤول <code>type=admin</code> لقناة <code>private-admin-notifications</code>).
        </p>
    </div>

    @php
        $reverb = config('broadcasting.connections.reverb');
        $reverbOpts = $reverb['frontend'] ?? $reverb['options'] ?? [];
    @endphp
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const reverbKey = @json($reverb['key'] ?? '');
        const reverbHost = @json($reverbOpts['host'] ?? '127.0.0.1');
        const reverbPort = {{ (int) ($reverbOpts['port'] ?? 8080) }};
        const forceTLS = @json((bool) ($reverbOpts['useTLS'] ?? false));
        const authEndpoint = @json(url('/api/broadcasting/auth'));

        let pusher = null;
        let channel = null;
        const notifications = [];

        function connectWebSocket() {
            if (!reverbKey) {
                updateStatus(false, 'REVERB_APP_KEY غير مضبوط في الإعدادات');
                return;
            }

            pusher = new Pusher(reverbKey, {
                wsHost: reverbHost,
                wsPort: reverbPort,
                wssPort: reverbPort,
                forceTLS: forceTLS,
                enabledTransports: ['ws', 'wss'],
                cluster: 'mt1',
                authEndpoint: authEndpoint,
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            });

            pusher.connection.bind('connected', () => updateStatus(true, 'متصل — WebSocket (Reverb)'));
            pusher.connection.bind('disconnected', () => updateStatus(false, 'غير متصل'));
            pusher.connection.bind('error', () => updateStatus(false, 'خطأ اتصال'));

            channel = pusher.subscribe('private-admin-notifications');

            channel.bind('admin.notification', function (data) {
                addNotification(data.message || JSON.stringify(data));
            });

            channel.bind('pusher:subscription_error', (err) => {
                console.error('Subscription error:', err);
                updateStatus(false, 'فشل الاشتراك — تحقق من /api/broadcasting/auth والصلاحيات');
            });
        }

        function updateStatus(connected, text) {
            const el = document.getElementById('status');
            el.className = 'status ' + (connected ? 'connected' : 'disconnected');
            el.textContent = text;
        }

        function addNotification(message) {
            notifications.unshift({ message, time: new Date().toLocaleTimeString() });
            renderNotifications();
        }

        function renderNotifications() {
            const list = document.getElementById('notificationsList');
            document.getElementById('count').textContent = notifications.length;

            if (notifications.length === 0) {
                list.innerHTML = '<div class="empty">لا إشعارات بعد</div>';
                return;
            }

            list.innerHTML = notifications.map(n => `
                <div class="notification">
                    <div>${escapeHtml(n.message)}</div>
                    <div class="time">${n.time}</div>
                </div>
            `).join('');
        }

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        connectWebSocket();
        renderNotifications();
    </script>
</body>
</html>
