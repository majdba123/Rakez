# Notifications System Documentation

## Overview

This system provides 3 types of notifications:
1. **Admin Notifications** - Private channel for admins only
2. **User Notifications** - Private channel for specific user by ID
3. **Public Notifications** - Public channel for everyone (no auth)

---

## 🔑 Frontend Configuration

### Required Keys (from `.env`)

```env
REVERB_APP_KEY=your-app-key
REVERB_HOST=127.0.0.1        # or your-domain.com in production
REVERB_PORT=8080
REVERB_SCHEME=http           # or https in production
```

### JavaScript Setup

```html
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
```

```javascript
const pusher = new Pusher('YOUR_REVERB_APP_KEY', {
    wsHost: '127.0.0.1',        // REVERB_HOST
    wsPort: 8080,                // REVERB_PORT
    wssPort: 8080,
    forceTLS: false,             // true if REVERB_SCHEME=https
    enabledTransports: ['ws', 'wss'],
    cluster: 'mt1',
    // For private channels only:
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: { 'Authorization': 'Bearer ' + userToken }
    }
});
```

---

## 📡 Channels

| Channel Name | Type | Auth Required | Who Can Listen |
|--------------|------|---------------|----------------|
| `private-admin-notifications` | Private | Yes (admin token) | Admins only |
| `private-user-notifications.{userId}` | Private | Yes (user token) | Specific user |
| `public-notifications` | Public | No | Everyone |

> **Note:** With Laravel Reverb, bind to the event name directly (no dot prefix needed).
> Example: `admin.notification`, `user.notification`, `public.notification`

---

## 🎯 Events

### 1. AdminNotificationEvent

**Channel:** `private-admin-notifications`  
**Event Name:** `admin.notification`

**Backend Usage:**
```php
use App\Events\AdminNotificationEvent;

// Send to ALL admins
event(new AdminNotificationEvent('New employee added with ID: 5'));
```

**Frontend Listening:**
```javascript
// Must be logged in as admin
const channel = pusher.subscribe('private-admin-notifications');

channel.bind('admin.notification', function(data) {
    console.log(data.message);
    // Output: "New employee added with ID: 5"
});
```

**Data Structure:**
```json
{
    "message": "New employee added with ID: 5"
}
```

---

### 2. UserNotificationEvent

**Channel:** `private-user-notifications.{userId}`  
**Event Name:** `user.notification`

**Backend Usage:**
```php
use App\Events\UserNotificationEvent;

// Send to specific user (ID: 123)
event(new UserNotificationEvent(123, 'Your request was approved'));
```

**Frontend Listening:**
```javascript
// Must be logged in as that user
const userId = 123;
const channel = pusher.subscribe('private-user-notifications.' + userId);

channel.bind('user.notification', function(data) {
    console.log(data.message);
    // Output: "Your request was approved"
});
```

**Data Structure:**
```json
{
    "message": "Your request was approved"
}
```

---

### 3. PublicNotificationEvent

**Channel:** `public-notifications`  
**Event Name:** `public.notification`

**Backend Usage:**
```php
use App\Events\PublicNotificationEvent;

// Send to everyone (no auth needed)
event(new PublicNotificationEvent('System maintenance at 10pm'));
```

**Frontend Listening:**
```javascript
// NO authentication needed
const channel = pusher.subscribe('public-notifications');

channel.bind('public.notification', function(data) {
    console.log(data.message);
    // Output: "System maintenance at 10pm"
});
```

**Data Structure:**
```json
{
    "message": "System maintenance at 10pm"
}
```

---

## 🌐 API Endpoints

### Admin APIs (require admin token)

| Method | Endpoint | Body | Description |
|--------|----------|------|-------------|
| `GET` | `/api/admin/notifications` | - | Get admin's notifications |
| `POST` | `/api/admin/notifications/send-to-user` | `{"user_id": 5, "message": "..."}` | Send to user |
| `POST` | `/api/admin/notifications/send-public` | `{"message": "..."}` | Send public |
| `GET` | `/api/admin/notifications/user/{userId}` | - | Get user's notifications |
| `GET` | `/api/admin/notifications/public` | - | Get all public |

### User API (require user token)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/user/notifications` | Get my notifications (private + public) |

### Public API (no auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/public/notifications` | Get public notifications |

---

## 🔐 Channel Authorization (routes/channels.php)

```php
// Admin channel - only admin users
Broadcast::channel('admin-notifications', function (User $user) {
    return $user->type === 'admin';
});

// User channel - only the specific user
Broadcast::channel('user-notifications.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

// Public channel - no authorization needed (automatic)
```

---

## 📱 Complete Frontend Examples

### Admin Page Example

```javascript
// 1. Login and get token
const loginRes = await fetch('/api/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: 'admin@example.com', password: 'password' })
});
const { token } = await loginRes.json();

// 2. Connect to WebSocket
const pusher = new Pusher('YOUR_REVERB_APP_KEY', {
    wsHost: '127.0.0.1',
    wsPort: 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    cluster: 'mt1',
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: { 'Authorization': 'Bearer ' + token }
    }
});

// 3. Subscribe to admin channel
const channel = pusher.subscribe('private-admin-notifications');

// 4. Listen for notifications
channel.bind('admin.notification', function(data) {
    alert('New notification: ' + data.message);
});
```

### User Page Example

```javascript
const userId = 123; // Current user's ID
const token = 'user-token';

const pusher = new Pusher('YOUR_REVERB_APP_KEY', {
    wsHost: '127.0.0.1',
    wsPort: 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    cluster: 'mt1',
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: { 'Authorization': 'Bearer ' + token }
    }
});

// Subscribe to YOUR channel only
const channel = pusher.subscribe('private-user-notifications.' + userId);

channel.bind('user.notification', function(data) {
    alert('You have a notification: ' + data.message);
});
```

### Public Page Example

```javascript
// NO token needed!
const pusher = new Pusher('YOUR_REVERB_APP_KEY', {
    wsHost: '127.0.0.1',
    wsPort: 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    cluster: 'mt1'
    // NO authEndpoint needed for public channels
});

// Subscribe to public channel (no 'private-' prefix)
const channel = pusher.subscribe('public-notifications');

channel.bind('public.notification', function(data) {
    alert('Public announcement: ' + data.message);
});
```

---

## 🧪 Test Pages

| URL | Description |
|-----|-------------|
| `/notifications/admin` | Test admin notifications |
| `/notifications/user` | Test user notifications |
| `/notifications/public` | Test public notifications |

---

## 🚀 Running the System

### Local Development

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Reverb WebSocket server
php artisan reverb:start
```

### Production (Supervisor)

```ini
[program:reverb]
command=php /var/www/your-project/artisan reverb:start
autostart=true
autorestart=true
user=www-data
```

---

## 📊 Database Tables

### admin_notifications
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Admin user ID |
| message | text | Notification message |
| status | enum | pending / read |
| created_at | timestamp | - |

### user_notifications
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint (nullable) | User ID (NULL = public) |
| message | text | Notification message |
| status | enum | pending / read |
| created_at | timestamp | - |

---

## 🎯 Quick Reference

| Action | Backend Code |
|--------|-------------|
| Notify all admins | `event(new AdminNotificationEvent('message'))` |
| Notify specific user | `event(new UserNotificationEvent($userId, 'message'))` |
| Notify everyone | `event(new PublicNotificationEvent('message'))` |

| Channel | Subscribe Code | Event Name |
|---------|---------------|------------|
| Admin | `pusher.subscribe('private-admin-notifications')` | `admin.notification` |
| User | `pusher.subscribe('private-user-notifications.' + userId)` | `user.notification` |
| Public | `pusher.subscribe('public-notifications')` | `public.notification` |

