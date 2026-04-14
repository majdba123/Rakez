<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>User Notifications — اختبار فوري</title>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #0d1117; color: #c9d1d9; min-height: 100vh; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 20px; color: #58a6ff; }
        .status { padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .status.connected { background: #238636; color: #aff5b4; }
        .status.disconnected { background: #da3633; color: #ffa198; }
        .user-info { background: #161b22; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #30363d; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .user-info form { display: inline; }
        .user-info button { padding: 8px 14px; background: #da3633; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        .notifications { background: #161b22; border-radius: 12px; padding: 20px; border: 1px solid #30363d; }
        .notification { background: #0d1117; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-right: 4px solid #58a6ff; }
        .notification .time { font-size: 12px; color: #8b949e; margin-top: 5px; }
        .empty { text-align: center; color: #8b949e; padding: 40px; }
        .badge { background: #f85149; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        .hint { font-size: 13px; color: #8b949e; margin-top: 12px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
        <h1>إشعارات المستخدم — اختبار فوري</h1>

        <div id="status" class="status disconnected">غير متصل</div>

        <div class="user-info">
            <div>
                <p>المستخدم: <strong>{{ auth()->user()->name }}</strong></p>
                <p>المعرف: <strong id="userIdLabel">{{ auth()->id() }}</strong></p>
            </div>
            <form action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit">تسجيل الخروج</button>
            </form>
        </div>

        <div class="notifications">
            <h3>إشعاراتي <span id="count" class="badge">0</span></h3>
            <div id="notificationsList"></div>
        </div>

        <p class="hint">
            جرّب: <code style="direction:ltr;display:inline-block;">GET {{ url('/test/broadcast/user') }}/{{ auth()->id() }}</code>
            لإرسال حدث إلى <code style="direction:ltr;">private-user-notifications.{{ auth()->id() }}</code>.
        </p>
    </div>

    @php
        $reverb = config('broadcasting.connections.reverb');
        $reverbOpts = $reverb['frontend'] ?? $reverb['options'] ?? [];
    @endphp
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const userId = {{ (int) auth()->id() }};
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

            const channelName = 'private-user-notifications.' + userId;
            channel = pusher.subscribe(channelName);

            channel.bind('pusher:subscription_succeeded', () => {
                console.log('Subscribed:', channelName);
            });

            channel.bind('user.notification', function (data) {
                const message = data.message || JSON.stringify(data);
                addNotification(message);
            });

            channel.bind('pusher:subscription_error', (err) => {
                console.error('Subscription error:', err);
                updateStatus(false, 'فشل الاشتراك — تحقق من /api/broadcasting/auth');
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
