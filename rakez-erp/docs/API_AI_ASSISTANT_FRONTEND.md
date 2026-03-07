# AI Assistant API — Frontend Reference

Base URL for all requests: **`/api`** (e.g. `https://your-domain.com/api`).

All AI routes require **authentication** (Bearer token or session). Send the auth token in the request (see Headers below).

---

## Headers (all requests)

| Header | Required | Value |
|--------|----------|--------|
| `Authorization` | Yes (if using token) | `Bearer {your_sanctum_token}` |
| `Content-Type` | Yes (for POST) | `application/json` |
| `Accept` | For streaming | `text/event-stream, application/json` |
| `X-Requested-With` | If SPA | `XMLHttpRequest` |

---

## Routes Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/ai/sections` | List available sections (contexts) for the user |
| POST | `/api/ai/ask` | One-shot question (no session) |
| POST | `/api/ai/chat` | Chat message — **JSON** or **SSE stream** |
| GET | `/api/ai/conversations` | List user's chat sessions |
| DELETE | `/api/ai/conversations/{sessionId}` | Delete a session |
| POST | `/api/ai/assistant/chat` | Knowledge-base assistant (separate flow) |

---

## 1. GET `/api/ai/sections`

Returns sections the current user can use (e.g. general, contracts, units). Use for section selector in the UI.

**Request**
- No body.
- Headers: `Authorization`, `Accept: application/json`.

**Response 200**
```json
{
  "success": true,
  "data": [
    { "key": "general", "label": "General" },
    { "key": "contracts", "label": "Contracts" },
    { "key": "units", "label": "Units" }
  ]
}
```

**Error 401** — Unauthorized (missing or invalid token).

---

## 2. POST `/api/ai/ask`

One-shot question. Creates a new session and returns one reply. No conversation history.

**Request body**
```json
{
  "question": "What can I do in this system?",
  "section": "general",
  "context": {}
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `question` | string | Yes | Max 2000 chars |
| `section` | string | No | One of section keys from GET /api/ai/sections |
| `context` | object | No | Section-specific context (e.g. `{ "contract_id": 123 }` for section `contracts`) |

**Response 200**
```json
{
  "success": true,
  "data": {
    "message": "You can use the ERP to manage contracts, units...",
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "conversation_id": 42,
    "suggestions": ["How do I navigate?", "Explain statuses."],
    "error_code": null,
    "steps": [],
    "links": [],
    "access_summary": null,
    "meta": {
      "session_id": "550e8400-e29b-41d4-a716-446655440000",
      "section": "general",
      "tokens": 150,
      "latency_ms": 1200
    }
  }
}
```

**Error 422** — Validation (e.g. `question` missing, `section` invalid).  
**Error 4xx/5xx** — `{ "success": false, "error_code": "...", "message": "..." }`.

---

## 3. POST `/api/ai/chat` — Full JSON (no streaming)

Use when you want the **entire reply in one response** (classic request/response).

**Request body**
```json
{
  "message": "How do I create a contract?",
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "section": "general",
  "context": {},
  "stream": false
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `message` | string | Yes | Max 2000 chars |
| `session_id` | string (UUID) | No | Omit for new session; send to continue conversation |
| `section` | string | No | Section key from GET /api/ai/sections |
| `context` | object | No | Section-specific (e.g. `contract_id`, `unit_id`) |
| `stream` | boolean | No | `false` or omit → JSON response |

**Response 200**
```json
{
  "success": true,
  "data": {
    "message": "To create a contract, go to Contracts and click...",
    "session_id": "550e8400-e29b-41d4-a716-446655440000",
    "conversation_id": 43,
    "suggestions": ["How do I edit?", "What are statuses?"],
    "error_code": null,
    "steps": [],
    "links": [],
    "access_summary": null,
    "meta": {
      "session_id": "550e8400-e29b-41d4-a716-446655440000",
      "section": "general",
      "tokens": 200,
      "latency_ms": 1500
    }
  }
}
```

- Use `data.session_id` for the next message in the same thread.

---

## 4. POST `/api/ai/chat` — Streaming (SSE)

Use when you want **word-by-word streaming** (e.g. ChatGPT-style). Send **`stream: true`** and use **`Accept: text/event-stream`**.

**Request body**
```json
{
  "message": "Explain contract statuses.",
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "section": "contracts",
  "context": { "contract_id": 1 },
  "stream": true
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `message` | string | Yes | Max 2000 chars |
| `session_id` | string (UUID) | No | Omit for new session |
| `section` | string | No | Section key |
| `context` | object | No | Section-specific context |
| `stream` | boolean | Yes for SSE | **Must be `true`** (boolean, not string) |

**Headers**
- `Accept: text/event-stream, application/json` (so backend can send SSE; fallback is JSON).
- `Authorization: Bearer {token}`.

**Response 200**
- **Content-Type:** `text/event-stream`
- **Body:** Server-Sent Events (SSE), one event per line. Each event is a line starting with `data: `.

**SSE event types**

1. **Text chunk** (streaming text):
   ```
   data: {"chunk":"مرحباً"}
   data: {"chunk":" "}
   data: {"chunk":"كيف"}
   data: {"chunk":" "}
   data: {"chunk":"يمكنني"}
   data: {"chunk":" "}
   data: {"chunk":"مساعدتك؟"}
   ```

2. **Final metadata** (after all chunks):
   ```
   data: {"session_id":"550e8400-e29b-41d4-a716-446655440000","conversation_id":44,"done":true}
   ```

3. **End of stream**:
   ```
   data: [DONE]
   ```

4. **Error** (if something fails):
   ```
   data: {"error":true,"error_code":"UNAUTHORIZED_SECTION","message":"You do not have access to this section."}
   data: [DONE]
   ```
   or:
   ```
   data: {"error":true,"message":"An unexpected error occurred."}
   data: [DONE]
   ```

**Frontend flow (streaming)**
1. Send POST with `stream: true` and `Accept: text/event-stream`.
2. If `Content-Type` of response is `text/event-stream`:
   - Read body as stream (e.g. `fetch` + `response.body.getReader()`).
   - Parse lines: split by `\n`, for each line starting with `data: ` take the rest and:
     - If payload is `[DONE]` → stop.
     - Else parse as JSON: if `chunk` → append to message; if `session_id` / `done` → store session for next request; if `error` → show error and stop.
3. If `Content-Type` is `application/json` (fallback when backend does not stream):
   - Parse JSON and show `data.message` at once.

**Example (JavaScript)**
```javascript
const res = await fetch('/api/ai/chat', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'text/event-stream',
  },
  body: JSON.stringify({ message: 'Hello', stream: true }),
});

if (res.headers.get('content-type')?.includes('text/event-stream')) {
  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });
    const lines = buffer.split('\n');
    buffer = lines.pop() ?? '';
    for (const line of lines) {
      if (!line.startsWith('data: ')) continue;
      const payload = line.slice(6);
      if (payload === '[DONE]') return;
      try {
        const data = JSON.parse(payload);
        if (data.chunk) appendToUI(data.chunk);
        if (data.session_id) sessionId = data.session_id;
        if (data.error) showError(data.message);
      } catch (_) {}
    }
  }
} else {
  const json = await res.json();
  appendToUI(json.data.message);
  sessionId = json.data.session_id;
}
```

---

## 5. GET `/api/ai/conversations`

List the current user's chat sessions (for sidebar/history).

**Query parameters**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `per_page` | int | 20 | Items per page |
| `section` | string | — | Filter by section key |

**Example:** `GET /api/ai/conversations?per_page=30&section=general`

**Response 200**
```json
{
  "success": true,
  "data": [
    {
      "session_id": "550e8400-e29b-41d4-a716-446655440000",
      "section": "general",
      "last_message": "To create a contract, go to...",
      "last_message_at": "2025-03-06T12:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 30,
    "total": 5
  }
}
```

---

## 6. DELETE `/api/ai/conversations/{sessionId}`

Delete one chat session. `sessionId` must be a valid UUID.

**Example:** `DELETE /api/ai/conversations/550e8400-e29b-41d4-a716-446655440000`

**Response 200**
```json
{
  "success": true,
  "data": {
    "deleted": true
  }
}
```

**Response 403** — Not the owner of that session.  
**Response 200 with `deleted: false`** — Session not found (or already deleted).

---

## Section context (optional)

When `section` is set, you can send extra context so the AI can answer in context (e.g. current contract).

| Section | Allowed `context` keys | Example |
|---------|------------------------|--------|
| `general` | — | `{}` |
| `contracts` | `contract_id` (int) | `{ "contract_id": 123 }` |
| `units` | `contract_id`, `unit_id` (int) | `{ "contract_id": 123, "unit_id": 456 }` |
| `second_party` | `contract_id` | `{ "contract_id": 123 }` |

Validation: if you send invalid types (e.g. string for `contract_id`), you get **422** with `errors` object.

---

## Error response shape (JSON)

For **4xx/5xx** (and when not streaming):

```json
{
  "success": false,
  "error_code": "UNAUTHORIZED_SECTION",
  "message": "You do not have access to this section."
}
```

Common codes: `UNAUTHORIZED_SECTION`, `AI_DISABLED`, `BUDGET_EXCEEDED`, etc.

---

## Quick reference

| Goal | Method | Endpoint | Body |
|------|--------|----------|------|
| Sections for dropdown | GET | `/api/ai/sections` | — |
| One question, one reply | POST | `/api/ai/ask` | `{ "question", "section?", "context?" }` |
| Chat reply (full JSON) | POST | `/api/ai/chat` | `{ "message", "session_id?", "section?", "context?", "stream": false }` |
| Chat reply (streaming) | POST | `/api/ai/chat` | `{ "message", "session_id?", "section?", "context?", "stream": true }` + `Accept: text/event-stream` |
| List sessions | GET | `/api/ai/conversations?per_page=20&section=` | — |
| Delete session | DELETE | `/api/ai/conversations/{sessionId}` | — |

All under **`/api`**, all require **auth** (e.g. `Authorization: Bearer {token}`).
