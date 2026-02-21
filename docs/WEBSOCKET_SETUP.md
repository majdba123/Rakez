# WebSocket Setup Guide (A to Z)

Complete guide to configure Laravel Reverb WebSocket from scratch.

---

## 1. Install Broadcasting

```bash
php artisan install:broadcasting
```

This creates:
- `config/broadcasting.php`
- `routes/channels.php`
- Adds Reverb config to `.env`

---

## 2. Configure `.env`

### Local Development
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=887940
REVERB_APP_KEY=jgpli2fbp0v6n0jaqdqo
REVERB_APP_SECRET=kz2lgk62j5cgu81el1ix
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Production Server
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=887940
REVERB_APP_KEY=jgpli2fbp0v6n0jaqdqo
REVERB_APP_SECRET=kz2lgk62j5cgu81el1ix
REVERB_HOST=your-domain.com
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=your-domain.com
VITE_REVERB_PORT=80
VITE_REVERB_SCHEME=http
```

---

## 3. Create Events

### Admin Notification Event
`app/Events/AdminNotificationEvent.php`
```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AdminNotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin-notifications')];
    }

    public function broadcastAs(): string
    {
        return 'admin.notification';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
```

### User Notification Event
`app/Events/UserNotificationEvent.php`
```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class UserNotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public int $userId;
    public string $message;

    public function __construct(int $userId, string $message)
    {
        $this->userId = $userId;
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user-notifications.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'user.notification';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
```

### Public Notification Event
`app/Events/PublicNotificationEvent.php`
```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PublicNotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [new Channel('public-notifications')];
    }

    public function broadcastAs(): string
    {
        return 'public.notification';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
```

---

## 4. Configure Channel Authorization

`routes/channels.php`
```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Admin channel - only admin users
Broadcast::channel('admin-notifications', function (User $user) {
    return $user->type === 'admin';
});

// User channel - only the specific user
Broadcast::channel('user-notifications.{userId}', function (User $user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Public channel - no authorization needed
```

---

## 5. Add Broadcasting Auth Route

`routes/api.php`
```php
use Illuminate\Support\Facades\Broadcast;

// Add at top of file
Broadcast::routes(['middleware' => ['auth:sanctum']]);
```

---

## 6. How to Dispatch Events

```php
use App\Events\AdminNotificationEvent;
use App\Events\UserNotificationEvent;
use App\Events\PublicNotificationEvent;

// Send to all admins
event(new AdminNotificationEvent('New employee added with ID: 5'));

// Send to specific user (ID: 123)
event(new UserNotificationEvent(123, 'Your request was approved'));

// Send to everyone (public)
event(new PublicNotificationEvent('System maintenance at 10pm'));
```

---

## 7. Run Locally

Open 2 terminals:

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Reverb WebSocket server
php artisan reverb:start
```

---

## 8. Deploy to Production Server

### Step 1: Install Supervisor
```bash
sudo apt update
sudo apt install supervisor -y
```

### Step 2: Create Reverb Config
```bash
sudo nano /etc/supervisor/conf.d/reverb.conf
```

Add:
```ini
[program:reverb]
process_name=%(program_name)s
command=php /var/www/your-project/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/your-project/storage/logs/reverb.log
stopwaitsecs=3600
```

### Step 3: Start Reverb
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb
```

### Step 4: Check Status
```bash
sudo supervisorctl status reverb
```

### Step 5: Configure Nginx
Add to your Nginx config:
```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
}
```

### Step 6: Restart Nginx
```bash
sudo nginx -t
sudo systemctl restart nginx
```

---

## 9. Useful Commands

```bash
# Start Reverb
sudo supervisorctl start reverb

# Stop Reverb
sudo supervisorctl stop reverb

# Restart Reverb
sudo supervisorctl restart reverb

# Check Status
sudo supervisorctl status reverb

# View Logs
tail -f /var/www/your-project/storage/logs/reverb.log

# Clear Caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

---

## 10. Troubleshooting

| Problem | Solution |
|---------|----------|
| Reverb not starting | Check logs: `tail -f storage/logs/reverb.log` |
| Connection refused | Ensure port 8080 is open, Nginx proxy configured |
| "Unauthenticated" on channel | Check token is valid, channel auth in `routes/channels.php` |
| Event not received | Check event name matches: `channel.bind('event.name', ...)` |

