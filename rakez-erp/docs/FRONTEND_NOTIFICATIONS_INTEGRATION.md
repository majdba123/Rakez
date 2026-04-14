# Notifications — frontend integration (real-time)

This document covers **broadcast notification channels** used by the test pages and backend events. REST listing APIs for in-app notifications may exist separately; here we focus on **WebSocket / Pusher-compatible (Reverb)** integration.

API host example: `https://api.rakez.com.sa`  
Broadcasting auth endpoint: **`POST /api/broadcasting/auth`** (Sanctum).

---

## 1. Environment keys (same as chat / Echo)

Use the **same Reverb (Vite) variables** as chat so one Echo client can subscribe to both chat and notification channels.

| Key | Example | Purpose |
|-----|---------|---------|
| `VITE_REVERB_APP_KEY` | *(= server `REVERB_APP_KEY`)* | Public app key. |
| `VITE_REVERB_HOST` | `api.rakez.com.sa` | Browser WebSocket host. |
| `VITE_REVERB_PORT` | `443` | **`wss`** port in production (usually **443**). |
| `VITE_REVERB_SCHEME` | `https` | Use **`wss`** when `https`. |

Optional for REST only: `VITE_API_BASE_URL` = `https://api.rakez.com.sa/api`.

Reference: `resources/js/bootstrap.js`, `config/broadcasting.php` (`frontend` block mirrors these for Blade demos).

---

## 2. Authentication for private channels

| Client type | How to authorize `/api/broadcasting/auth` |
|-------------|---------------------------------------------|
| **Token (mobile / SPA)** | `Authorization: Bearer {token}` |
| **Same-origin web** | Session cookie + `X-CSRF-TOKEN` + `Accept: application/json` |

**Public** channel below does **not** call `/api/broadcasting/auth`.

---

## 3. Channels and events

### A. User-scoped (private)

| Pusher / Echo subscription | Channel name | Who may listen |
|----------------------------|--------------|----------------|
| `private-user-notifications.{userId}` | `user-notifications.{userId}` | Only the user whose `id` is **`userId`**. |

**Event name:** `user.notification` (listen as `.user.notification` in Echo with dot prefix, or bind `user.notification` in pusher-js).

**Payload:**

```json
{
  "message": "string"
}
```

**Backend event class:** `App\Events\UserNotificationEvent`

---

### B. Admin (private)

| Subscription | Channel name | Who may listen |
|--------------|--------------|----------------|
| `private-admin-notifications` | `admin-notifications` | Users with **`user.type === 'admin'`** (see `routes/channels.php`). |

**Event name:** `admin.notification`

**Payload:**

```json
{
  "message": "string"
}
```

**Backend event class:** `App\Events\AdminNotificationEvent`

---

### C. Public (no auth)

| Subscription | Channel name | Auth |
|----------------|--------------|------|
| `public-notifications` | `public-notifications` | None |

**Event name:** `public.notification`

**Payload:**

```json
{
  "message": "string"
}
```

**Backend event class:** `App\Events\PublicNotificationEvent`

---

## 4. Laravel Echo examples

### User notifications

```javascript
window.Echo.private(`user-notifications.${userId}`)
  .listen('.user.notification', (e) => {
    console.log(e.message);
  });
```

### Admin notifications

```javascript
window.Echo.private('admin-notifications')
  .listen('.admin.notification', (e) => {
    console.log(e.message);
  });
```

### Public notifications

```javascript
window.Echo.channel('public-notifications')
  .listen('.public.notification', (e) => {
    console.log(e.message);
  });
```

---

## 5. pusher-js (without Echo)

If you use **pusher-js** directly, set:

- `authEndpoint`: full URL to **`https://{api-host}/api/broadcasting/auth`**
- For private channels: same auth headers as above (Bearer and/or CSRF)

Channel names on the wire:

- `private-user-notifications.{userId}`
- `private-admin-notifications`
- `public-notifications` (no `private-` prefix)

Event binding names: `user.notification`, `admin.notification`, `public.notification` (library-specific; see Pusher docs for private channel event prefixes).

---

## 6. Test routes (Blade, same backend)

| URL | Auth | Notes |
|-----|------|--------|
| `/notifications/public` | No | Public channel only. |
| `/notifications/user` | Web session | Uses session + CSRF for private user channel. |
| `/notifications/admin` | Web session + admin | `type === 'admin'`. |

These pages demonstrate Reverb config via `config('broadcasting.connections.reverb.frontend')`; your SPA should use **VITE_*** instead.

**Debug broadcast triggers (GET, dev only):**

- `/test/broadcast/user/{userId}`
- `/test/broadcast/admin`
- `/test/broadcast/public`

---

## 7. Reference files

- Channel authorization: `routes/channels.php`
- Events: `app/Events/UserNotificationEvent.php`, `AdminNotificationEvent.php`, `PublicNotificationEvent.php`
- Echo bootstrap: `resources/js/bootstrap.js`
- Blade demos: `resources/views/notifications/*.blade.php`
- Web routes: `routes/web.php` (`/notifications/*`, `/test/broadcast/*`)
