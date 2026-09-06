# Frontend WebSocket Guide

Guide for subscribing to Laravel Reverb/Pusher-compatible channels without hardcoding production infrastructure or credentials.

## Configuration

Expose only the client-safe values required by the frontend:

```env
VITE_REVERB_APP_KEY=<client-safe-app-key>
VITE_REVERB_HOST=<your-domain.example>
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
VITE_API_BASE_URL=https://<your-domain.example>/api
```

Never expose `REVERB_APP_SECRET`, database credentials, API secrets, or privileged access tokens to the frontend bundle.

## Install

```bash
npm install pusher-js
```

## Pusher/Reverb client factory

```javascript
import Pusher from 'pusher-js';

export function createRealtimeClient(token = null) {
    const client = new Pusher(import.meta.env.VITE_REVERB_APP_KEY, {
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${import.meta.env.VITE_API_BASE_URL}/broadcasting/auth`,
        auth: token
            ? { headers: { Authorization: `Bearer ${token}` } }
            : undefined,
    });

    return client;
}
```

## Private channel example

```javascript
const client = createRealtimeClient(userToken);
const channel = client.subscribe(`private-user-notifications.${userId}`);

channel.bind('user.notification', (data) => {
    console.log('Notification received', data);
});
```

The backend must authorize that the authenticated user is permitted to subscribe to the requested channel. Client-side user IDs are never a security boundary.

## Public channel example

```javascript
const client = createRealtimeClient();
const channel = client.subscribe('public-notifications');

channel.bind('public.notification', (data) => {
    console.log('Public notification received', data);
});
```

Use public channels only for data that is safe for every connected client to receive.

## Cleanup

```javascript
channel.unbind_all();
client.unsubscribe(channel.name);
client.disconnect();
```

## Production checklist

- Use WSS/HTTPS in production.
- Do not hardcode server IPs in source code.
- Do not commit real tokens or secrets to documentation.
- Store authentication tokens using an approach appropriate to the application's threat model; avoid exposing long-lived privileged tokens to JavaScript.
- Confirm private-channel authorization is enforced server-side.
- Restrict CORS and allowed origins to the intended frontend domains.
- Rotate credentials immediately if they have ever been committed to Git history.
