# Rakiz AI Assistant v2 – Vue Frontend Plan (Separate Repo)

This document is the **developer plan** for implementing the AI Assistant v2 frontend in your **separate Vue project**. The backend (Laravel) exposes the v2 API; the Vue app will consume it.

---

## 1. Backend API Base

- **Base URL**: Same as your existing ERP API (e.g. `https://api.rakez.com/api` or `http://localhost:8000/api`).
- **Auth**: **Bearer token** (Sanctum). Send `Authorization: Bearer <token>` on every request. Use the same login/token flow as the rest of your Vue app.
- **Permission**: Backend requires `use-ai-assistant`. Users without it receive **403**.

---

## 2. Postman Collection (Developers)

Use the Postman collection to test the API without the Vue app:

- **File**: `docs/postman/collections/RAKEZ_ERP_AI_V2.postman_collection.json`
- **Environment**: Use the same as main ERP (`Rakez-ERP-Local` or your copy) with `base_url`, `user_email`, `user_password`. Run **00 - Auth → Login** then **01 - Chat**, **02 - Search**, **03 - Explain Access**.

Import path from repo root:  
`rakez-erp/docs/postman/collections/RAKEZ_ERP_AI_V2.postman_collection.json`

---

## 3. API Endpoints Summary

| Method | Path | Purpose |
|--------|------|--------|
| POST | `/ai/v2/chat` | Main chat; returns strict JSON (answer, sources, links, etc.). |
| POST | `/ai/v2/search` | RAG-only; returns `sources` array. |
| POST | `/ai/v2/explain-access` | Explain route/entity access; returns `allowed`, `missing_permissions`, `suggested_routes`. |

All require **Accept: application/json**, **Content-Type: application/json**, and **Authorization: Bearer &lt;token&gt;**.

---

## 4. Request / Response Shapes (for Vue)

### 4.1 POST `/ai/v2/chat`

**Request body:**

```json
{
  "message": "How many leads do we have?",
  "session_id": null,
  "page_context": {
    "route": "/marketing/leads",
    "entity_id": null,
    "entity_type": null,
    "filters": {}
  }
}
```

- `message` (string, required): User message.
- `session_id` (string | null): Optional; for conversation continuity if you implement it later.
- `page_context` (object, optional): Current page context sent to the model.
  - `route`: Current route/path (e.g. `/marketing/leads`).
  - `entity_id`: ID of the current entity if viewing one (e.g. lead id).
  - `entity_type`: e.g. `"lead"`, `"contract"`.
  - `filters`: Any active list filters (key-value).

**Response (200):**

```json
{
  "success": true,
  "data": {
    "answer_markdown": "You have **12** leads in the system.",
    "confidence": "high",
    "sources": [
      { "type": "record", "title": "Lead: Acme", "ref": "lead/1", "excerpt": "...", "link": null }
    ],
    "links": [
      { "label": "Leads", "route": "api/marketing/leads", "why": "View all leads" }
    ],
    "suggested_actions": [
      { "action": "View lead", "needs_confirmation": false, "route": "/marketing/leads/1" }
    ],
    "follow_up_questions": ["Show me the latest leads", "How many are new?"],
    "access_notes": {
      "had_denied_request": false,
      "reason": ""
    }
  }
}
```

- **Safe rendering**: Treat `answer_markdown` as **Markdown**. Either render with a Markdown component and **sanitize with DOMPurify** (or equivalent), or render as **plain text** (no raw HTML) to avoid XSS. Plan requires safe rendering; do not inject unsanitized HTML.
- When `access_notes.had_denied_request === true`, you can optionally call **Explain Access** and show a short explanation or link to request access.

### 4.2 POST `/ai/v2/search`

**Request body:**

```json
{
  "query": "marketing tasks",
  "filters": {},
  "limit": 10
}
```

**Response (200):**

```json
{
  "success": true,
  "data": {
    "sources": [
      { "type": "record", "title": "...", "ref": "...", "excerpt": "...", "link": null }
    ]
  }
}
```

Use this if you want a “search-only” UI (e.g. search bar that shows RAG sources without a full chat reply).

### 4.3 POST `/ai/v2/explain-access`

**Request body:**

```json
{
  "route": "/api/marketing/leads",
  "entity_type": "lead",
  "entity_id": 1
}
```

- `route` (string, required).
- `entity_type` (string, optional).
- `entity_id` (number, optional).

**Response (200):**

```json
{
  "success": true,
  "data": {
    "allowed": false,
    "missing_permissions": ["marketing.projects.view"],
    "human_reason": "You do not have permission to view this resource.",
    "suggested_routes": [
      { "route": "api/marketing/dashboard", "label": "Marketing Dashboard", "permission": "..." }
    ]
  }
}
```

Use when `access_notes.had_denied_request === true` to show “Why can’t I see this?” and suggest links the user can access.

---

## 5. Vue App Integration Plan

### 5.1 API client (recommended)

In your Vue project (e.g. `src/services/aiAssistant.js` or `src/api/aiV2.js`):

- **Base URL**: Use your existing API base URL (env variable, e.g. `import.meta.env.VITE_API_BASE_URL`).
- **Auth**: Attach the same Bearer token you use for other ERP API calls (e.g. from Pinia store or axios interceptor).
- **Methods**:
  - `chat(message, sessionId = null, pageContext = {})` → POST `/ai/v2/chat`.
  - `search(query, filters = {}, limit = 10)` → POST `/ai/v2/search`.
  - `explainAccess(route, entityType = null, entityId = null)` → POST `/ai/v2/explain-access`.

Handle **403** (no `use-ai-assistant`) and **503** (AI disabled) in the UI (e.g. “AI is not available” or “Contact admin for access”).

### 5.2 Chat widget / page (recommended)

- **Where**: Global layout (e.g. floating button + panel) or a dedicated “AI Assistant” route.
- **Flow**:
  1. User types a message.
  2. Optional: send current route and entity (e.g. from Vue Router and current view) as `page_context`.
  3. Call `chat(message, sessionId, pageContext)`.
  4. Show `data.answer_markdown` (with safe Markdown rendering), `data.sources`, `data.links`, `data.follow_up_questions`.
  5. If `data.access_notes.had_denied_request` is true, optionally call `explainAccess(...)` and show a brief explanation or “Request access” hint.
- **Safe rendering**: Use a Markdown renderer + DOMPurify, or render `answer_markdown` as plain text only. Do **not** render raw HTML from the API without sanitization.

### 5.3 Optional: RAG-only search

- A search bar or “Search knowledge” that calls `search(query)` and displays `data.sources` (title, ref, excerpt, link) without calling chat.

### 5.4 Suggested routes / links

- Backend returns `links` and `suggested_routes` (from explain-access) that are **already filtered by user permissions**. You can map `route` to your Vue Router paths (e.g. strip `api/` prefix or map to named routes) and use them as navigation links.

---

## 6. Checklist for Developers (Vue Repo)

- [ ] Use same API base URL and Bearer token as rest of ERP.
- [ ] Implement API client: `chat`, `search`, `explainAccess` with correct request/response types.
- [ ] Handle 403 (no permission) and 503 (AI disabled).
- [ ] Chat UI: send `page_context` (route, entity_id, entity_type, filters) when available.
- [ ] Render `answer_markdown` safely (Markdown + sanitize or plain text only).
- [ ] When `access_notes.had_denied_request === true`, optionally call explain-access and show reason/suggested routes.
- [ ] Use Postman collection `RAKEZ_ERP_AI_V2.postman_collection.json` to verify requests before/during Vue implementation.
- [ ] Do not add AI frontend to the Laravel repo; all UI lives in the **separate Vue project**.

---

## 7. References (Laravel Repo)

- **Postman collection**: `docs/postman/collections/RAKEZ_ERP_AI_V2.postman_collection.json`
- **Postman README**: `docs/postman/README.md` (updated to list AI v2 collection)
- **Backend config**: `config/ai_assistant.php` (v2 section), `config/ui_routes.php` (suggested routes source)
- **README**: Rakiz AI Assistant v2 section (env, migrations, queue, scheduler)
