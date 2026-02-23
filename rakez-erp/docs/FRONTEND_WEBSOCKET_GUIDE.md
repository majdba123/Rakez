# Frontend WebSocket Guide

Guide for frontend developers to subscribe to WebSocket channels.

---

## Configuration Values (Give to Frontend)

```
APP_KEY: jgpli2fbp0v6n0jaqdqo
HOST: 143.198.24.230 (or your domain)
PORT: 80
SCHEME: http (or https)
AUTH_ENDPOINT: /api/broadcasting/auth
```

---

## Install Dependencies

```bash
npm install pusher-js
# or
yarn add pusher-js
```

---

## 3 Channels Available

| Channel | Type | Auth Required | Event Name |
|---------|------|---------------|------------|
| `private-admin-notifications` | Private | Yes (admin token) | `admin.notification` |
| `private-user-notifications.{userId}` | Private | Yes (user token) | `user.notification` |
| `public-notifications` | Public | No | `public.notification` |

---

## JavaScript Setup

### 1. Initialize Pusher

```javascript
import Pusher from 'pusher-js';

const pusher = new Pusher('jgpli2fbp0v6n0jaqdqo', {
    wsHost: '143.198.24.230',
    wsPort: 80,
    wssPort: 443,
    forceTLS: false,  // true if using https
    enabledTransports: ['ws', 'wss'],
    cluster: 'mt1',
    // Required for private channels:
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: {
            'Authorization': 'Bearer ' + userToken
        }
    }
});
```

---

## Subscribe to Channels

### Admin Channel (Private)

```javascript
// User must be admin
const token = 'admin-user-token';

const pusher = new Pusher('jgpli2fbp0v6n0jaqdqo', {
    wsHost: '143.198.24.230',
    wsPort: 80,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    cluster: 'mt1',
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: { 'Authorization': 'Bearer ' + token }
    }
});

const channel = pusher.subscribe('private-admin-notifications');

channel.bind('admin.notification', function(data) {
    console.log('Admin notification:', data.message);
});
```

### User Channel (Private)

```javascript
// User receives only their notifications
const token = 'user-token';
const userId = 123;

const pusher = new Pusher('jgpli2fbp0v6n0jaqdqo', {
    wsHost: '143.198.24.230',
    wsPort: 80,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    cluster: 'mt1',
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: { 'Authorization': 'Bearer ' + token }
    }
});

const channel = pusher.subscribe('private-user-notifications.' + userId);

channel.bind('user.notification', function(data) {
    console.log('User notification:', data.message);
});
```

### Public Channel (No Auth)

```javascript
// Anyone can subscribe - no token needed
const pusher = new Pusher('jgpli2fbp0v6n0jaqdqo', {
    wsHost: '143.198.24.230',
    wsPort: 80,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    cluster: 'mt1'
    // NO authEndpoint needed!
});

const channel = pusher.subscribe('public-notifications');

channel.bind('public.notification', function(data) {
    console.log('Public notification:', data.message);
});
```

---

## Vue.js Example

### Create Plugin: `src/plugins/pusher.js`

```javascript
import Pusher from 'pusher-js';

export function createPusher(token = null) {
    const config = {
        wsHost: import.meta.env.VITE_REVERB_HOST || '143.198.24.230',
        wsPort: parseInt(import.meta.env.VITE_REVERB_PORT) || 80,
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        cluster: 'mt1'
    };

    // Add auth for private channels
    if (token) {
        config.authEndpoint = '/api/broadcasting/auth';
        config.auth = {
            headers: { 'Authorization': 'Bearer ' + token }
        };
    }

    return new Pusher(import.meta.env.VITE_REVERB_APP_KEY, config);
}
```

### Use in Component

```vue
<template>
    <div>
        <h2>Notifications ({{ notifications.length }})</h2>
        <div v-for="n in notifications" :key="n.id">
            {{ n.message }}
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { createPusher } from '@/plugins/pusher';

const notifications = ref([]);
let pusher = null;
let channel = null;

onMounted(() => {
    const token = localStorage.getItem('token');
    const userId = localStorage.getItem('user_id');

    pusher = createPusher(token);

    // Subscribe to user's private channel
    channel = pusher.subscribe('private-user-notifications.' + userId);

    channel.bind('user.notification', (data) => {
        notifications.value.unshift({
            id: Date.now(),
            message: data.message
        });
    });
});

onUnmounted(() => {
    if (channel) channel.unbind_all();
    if (pusher) pusher.disconnect();
});
</script>
```

---

## React Example

```jsx
import { useEffect, useState } from 'react';
import Pusher from 'pusher-js';

function Notifications() {
    const [notifications, setNotifications] = useState([]);

    useEffect(() => {
        const token = localStorage.getItem('token');
        const userId = localStorage.getItem('user_id');

        const pusher = new Pusher('jgpli2fbp0v6n0jaqdqo', {
            wsHost: '143.198.24.230',
            wsPort: 80,
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
            cluster: 'mt1',
            authEndpoint: '/api/broadcasting/auth',
            auth: {
                headers: { 'Authorization': 'Bearer ' + token }
            }
        });

        const channel = pusher.subscribe('private-user-notifications.' + userId);

        channel.bind('user.notification', (data) => {
            setNotifications(prev => [data, ...prev]);
        });

        return () => {
            channel.unbind_all();
            pusher.disconnect();
        };
    }, []);

    return (
        <div>
            <h2>Notifications ({notifications.length})</h2>
            {notifications.map((n, i) => (
                <div key={i}>{n.message}</div>
            ))}
        </div>
    );
}
```

---

## Event Data Structure

All events return:
```json
{
    "message": "Your notification message here"
}
```

---

## Connection Events

```javascript
// Connection state
pusher.connection.bind('connected', () => {
    console.log('Connected to WebSocket');
});

pusher.connection.bind('disconnected', () => {
    console.log('Disconnected from WebSocket');
});

pusher.connection.bind('error', (err) => {
    console.error('Connection error:', err);
});

// Channel subscription
channel.bind('pusher:subscription_succeeded', () => {
    console.log('Subscribed to channel');
});

channel.bind('pusher:subscription_error', (err) => {
    console.error('Subscription error:', err);
});
```

---

## Quick Reference

| Action | Code |
|--------|------|
| Subscribe to admin | `pusher.subscribe('private-admin-notifications')` |
| Subscribe to user | `pusher.subscribe('private-user-notifications.' + userId)` |
| Subscribe to public | `pusher.subscribe('public-notifications')` |
| Listen to admin event | `channel.bind('admin.notification', callback)` |
| Listen to user event | `channel.bind('user.notification', callback)` |
| Listen to public event | `channel.bind('public.notification', callback)` |
| Disconnect | `pusher.disconnect()` |
| Unsubscribe | `pusher.unsubscribe('channel-name')` |

---

## Environment Variables (.env)

```env
VITE_REVERB_APP_KEY=jgpli2fbp0v6n0jaqdqo
VITE_REVERB_HOST=143.198.24.230
VITE_REVERB_PORT=80
VITE_REVERB_SCHEME=http
```

Access in code:
```javascript
import.meta.env.VITE_REVERB_APP_KEY
import.meta.env.VITE_REVERB_HOST
import.meta.env.VITE_REVERB_PORT
import.meta.env.VITE_REVERB_SCHEME
```

