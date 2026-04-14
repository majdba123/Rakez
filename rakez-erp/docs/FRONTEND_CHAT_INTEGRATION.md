# Chat — frontend integration (API + real-time)

Backend API base path (example): `https://api.rakez.com.sa/api`  
All chat routes are under **`/api/chat`** and require **`auth:sanctum`** (Bearer token and/or first-party session cookies, depending on your setup).

---

## 1. Environment keys (give these to the frontend build)

These are **Vite** variables (must be prefixed with `VITE_` so they are exposed to the browser). Align them with the same values the Laravel backend uses for Reverb.

| Key | Example | Purpose |
|-----|---------|---------|
| `VITE_API_BASE_URL` | `https://api.rakez.com.sa/api` | REST base URL (your app may use `import.meta.env` or a config file). |
| `VITE_REVERB_APP_KEY` | *(same as `REVERB_APP_KEY` on server)* | Reverb / Pusher **app key** (public). |
| `VITE_REVERB_HOST` | `api.rakez.com.sa` | WebSocket host **as the browser sees it** (often the API domain). |
| `VITE_REVERB_PORT` | `443` | Port for **`wss`** in production (usually **443** behind Nginx TLS, **not** the internal Reverb process port). |
| `VITE_REVERB_SCHEME` | `https` | Use `https` so the client uses **`wss`**. |

**Local example**

```env
VITE_API_BASE_URL=http://localhost:8000/api
VITE_REVERB_APP_KEY=your-reverb-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

**Production (typical)**

```env
VITE_API_BASE_URL=https://api.rakez.com.sa/api
VITE_REVERB_APP_KEY=your-reverb-key
VITE_REVERB_HOST=api.rakez.com.sa
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Reference in repo: `resources/js/bootstrap.js` (Laravel Echo + Reverb).

---

## 2. Authentication

- **SPA / mobile:** `POST /api/login` → use `Authorization: Bearer {access_token}` on API calls.
- **Same-origin web (cookies):** log in via web session; use `axios` / `fetch` with **`credentials: 'include'`** and send **`X-CSRF-TOKEN`** where required.
- **Private WebSocket channels:** browser must complete **`POST /api/broadcasting/auth`** (Sanctum + session or Bearer, per your integration).

Echo setup in this project uses:

- `authEndpoint: {origin}/api/broadcasting/auth`
- Headers: `X-CSRF-TOKEN`, `Accept: application/json`, and optionally `Authorization: Bearer …`

---

## 3. REST API — chat

Prefix: **`{API_BASE_URL}/chat`** (e.g. `https://api.rakez.com.sa/api/chat`).

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/conversations` | List conversations for the current user. |
| `GET` | `/conversations/{userId}` | Get or create a 1:1 conversation with another user (`userId` = other user’s id). |
| `GET` | `/conversations/{conversationId}/messages` | Paginated messages (`per_page`, max 100). |
| `POST` | `/conversations/{conversationId}/messages` | Send **text**, **voice**, or **attachment** (image / video / file; see below). |
| `PATCH` | `/conversations/{conversationId}/read` | Mark messages as read. |
| `DELETE` | `/messages/{messageId}` | Delete own message. |
| `GET` | `/unread-count` | Total unread count across conversations. |

### Text message

`POST /conversations/{conversationId}/messages`  
`Content-Type: application/json`

```json
{
  "message": "نص الرسالة"
}
```

### Voice message

`POST /conversations/{conversationId}/messages`  
`Content-Type: multipart/form-data`

| Field | Required | Notes |
|-------|----------|--------|
| `voice` | Yes (for voice) | Audio file (e.g. webm, mp3, m4a). Max **10 MB** (server rule). |
| `message` | No | Optional caption. |
| `voice_duration_seconds` | No | Integer, e.g. `12`. |

Do **not** set `Content-Type` manually when using `FormData` in the browser.

**Do not** send `voice` and `attachment` in the same request.

### Image / video / document (attachment)

`POST /conversations/{conversationId}/messages`  
`Content-Type: multipart/form-data`

| Field | Required | Notes |
|-------|----------|--------|
| `attachment` | Yes | File. Allowed extensions include: jpeg, png, gif, webp, bmp, mp4, webm, mov, avi, m4v, pdf, doc, docx, xls, xlsx, txt, csv, zip. Max **50 MB** (server rule). |
| `message` | No | Optional caption. |

The API sets **`type`** automatically from MIME: `image` (image/*), `video` (video/*), or **`file`** (everything else allowed by validation). Files are stored on the **`public`** disk under **`storage/app/public/chat/voice/{conversationId}/`** (same folder layout as voice audio).

### Message object (response / WebSocket payload shape)

Text:

- `type`: `"text"`
- `message`: string

Voice:

- `type`: `"voice"`
- `message`: string (caption or default label)
- `voice_url`: absolute URL to play/download (requires `storage:link` on server and correct public URL / `PUBLIC_STORAGE_ORIGIN` / `APP_URL` as documented for ops)

Image / video / file:

- `type`: `"image"` | `"video"` | `"file"`
- `message`: string (caption or default: صورة / فيديو / ملف)
- `attachment_url`: absolute URL
- `attachment_original_name`: original filename

---

## 4. Real-time (Laravel Echo + Reverb)

### Channel

- **Private:** `conversation.{conversationId}`  
- In Echo: `Echo.private('conversation.' + conversationId)`

Only participants in that conversation may subscribe (see `routes/channels.php`).

### Event

- **Broadcast name:** `message.sent`  
- In Echo: `.listen('.message.sent', callback)` (leading dot matches `broadcastAs`).

### Payload (same idea as `MessageSent::broadcastWith()`)

Includes: `id`, `conversation_id`, `sender_id`, `sender` `{ id, name, email }`, `type`, `message`, `voice_url`, `voice_duration_seconds`, `attachment_url`, `attachment_original_name`, `is_read`, `created_at`.

---

## 5. Minimal Echo example (conceptual)

```javascript
window.Echo.private(`conversation.${conversationId}`)
  .listen('.message.sent', (e) => {
    // e.type, e.message, e.voice_url, …
  });
```

Ensure the Echo instance uses the **VITE_REVERB_*** keys and the correct **`authEndpoint`** for your domain.

---

## 6. Ops checklist (for integrators)

- Reverb running; Nginx (or similar) terminates **`wss`** on **443** and proxies to Reverb if needed.
- `php artisan storage:link` for **`voice_url`** files under `/storage/...`.
- CORS / cookies / Sanctum **stateful domains** configured if the SPA is on another origin.

---

## 7. Reference files in this repo

- HTTP: `routes/api.php` (`chat` prefix), `app/Http/Controllers/ChatController.php`
- Broadcast: `app/Events/MessageSent.php`, `routes/channels.php`
- Echo defaults: `resources/js/bootstrap.js`
- Test UI: `resources/views/chat/test.blade.php`
