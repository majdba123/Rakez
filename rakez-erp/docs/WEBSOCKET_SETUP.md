# WebSocket Setup Guide

Production-safe guide for configuring Laravel Reverb without committing real credentials or infrastructure details.

## Security requirements

- Never commit `REVERB_APP_SECRET`, real tokens, passwords, or production host addresses.
- Keep production configuration in environment variables or a secrets manager.
- Any credential that has previously appeared in Git history must be rotated.
- Prefer HTTPS/WSS in production and terminate TLS through Nginx or another trusted reverse proxy.

## 1. Install broadcasting

```bash
php artisan install:broadcasting
```

## 2. Environment configuration

### Local development

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=<local-app-id>
REVERB_APP_KEY=<local-app-key>
REVERB_APP_SECRET=<local-app-secret>
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Production

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=<production-app-id>
REVERB_APP_KEY=<production-app-key>
REVERB_APP_SECRET=<production-app-secret>
REVERB_HOST=<your-domain.example>
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

The public frontend may receive the Reverb app key where required by the protocol, but server secrets must never be exposed to browser code.

## 3. Channel authorization

Private channels should be authorized through authenticated broadcast routes:

```php
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);
```

Example user-specific authorization:

```php
Broadcast::channel('user-notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

Avoid trusting a client-provided user ID without server-side authorization.

## 4. Run locally

```bash
php artisan serve
php artisan reverb:start
```

Run these in separate terminals during local development.

## 5. Production process manager

Example Supervisor configuration:

```ini
[program:reverb]
process_name=%(program_name)s
command=php /var/www/your-project/artisan reverb:start --host=127.0.0.1 --port=8080
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

## 6. Nginx reverse proxy

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

Validate configuration before reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 7. Operational checks

- Confirm private channels reject unauthenticated users.
- Confirm a user cannot subscribe to another user's private channel.
- Confirm production clients connect using WSS.
- Confirm secrets are absent from browser bundles and repository files.
- Review logs before sharing them externally because they may contain request or infrastructure details.
