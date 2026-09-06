# Real-Time Notifications — Laravel Reverb

This document describes the Reverb/Echo notification flow without embedding production credentials, raw server addresses, or shared access tokens.

## Architecture

```text
Authenticated API request
        │
        ▼
Laravel business/service layer
        │
        ├── Persist notification/event state
        │
        ▼
Broadcast event / notification
        │
        ▼
Laravel Reverb
        │
        ▼
Authorized private/public channel
        │
        ▼
Laravel Echo / frontend client
```

## Server configuration

Keep server credentials in `.env` or the deployment secret store:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=<reverb-app-id>
REVERB_APP_KEY=<reverb-app-key>
REVERB_APP_SECRET=<reverb-app-secret>
REVERB_HOST=<reverb-host.example>
REVERB_PORT=443
REVERB_SCHEME=https

REVERB_ALLOWED_ORIGINS=https://app.example.com
```

`REVERB_APP_SECRET` is server-only and must never be placed in frontend code or committed documentation.

The current `config/reverb.php` reads allowed origins from `REVERB_ALLOWED_ORIGINS`; production should use explicit trusted HTTPS origins rather than `*`.

## Frontend configuration

Only client-safe values should enter the frontend bundle:

```env
VITE_REVERB_APP_KEY=<client-safe-app-key>
VITE_REVERB_HOST=<reverb-host.example>
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Example Echo setup:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

## Channel authorization

Private channels must be authorized server-side. A client-controlled user ID or role value is not an authorization boundary.

Typical pattern:

```php
Broadcast::channel('user-notifications.{userId}', function (User $user, int $userId) {
    return (int) $user->id === $userId;
});
```

Administrative channels should verify the real role/permission policy used by the application rather than trusting client input.

## Local development

Run the application processes required by the current environment, for example:

```bash
php artisan serve
php artisan reverb:start
php artisan queue:work
```

Use local-only credentials generated for development. Do not reuse production credentials in local examples or test fixtures.

## Testing checklist

- Verify unauthenticated clients cannot subscribe to protected channels.
- Verify a user cannot subscribe to another user's private channel.
- Verify admin-only events are inaccessible to non-admin users.
- Verify event payloads contain only fields intended for recipients.
- Verify production `REVERB_ALLOWED_ORIGINS` contains only trusted origins.
- Verify TLS/WSS is enabled in production.
- Verify rate/connection limits appropriate to the deployment are configured.

## Credential exposure rule

If a Reverb key/secret or any other provider credential has ever been committed to Git history, treat it as exposed and rotate/revoke it at the provider. Removing the literal from the current branch does not invalidate historical copies.

See also:

- `docs/WEBSOCKET_SETUP.md`
- `docs/FRONTEND_WEBSOCKET_GUIDE.md`
- `SECURITY.md`
