# API Examples: AI Assistant

## Ask (Stateless)
`POST /api/ai/ask`

**Request Body:**
```json
{
    "question": "How many units are available in Riyadh?",
    "section": "units"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "There are currently 15 available units in Riyadh across 3 projects.",
        "session_id": "uuid-123",
        "suggestions": [
            "Show me units in Jeddah",
            "What is the average price?"
        ]
    }
}
```

## AI tools orchestrator (no URL version segment)

Stateless tool-calling runs through the orchestrator. Requires authentication (Sanctum) and appropriate permissions.

### Chat (non-streaming)
`POST /api/ai/tools/chat`

**Request Body (example):**
```json
{
    "message": "Summarize pipeline KPIs for this week",
    "section": "sales"
}
```

### Stream (SSE wrapper)
`POST /api/ai/tools/stream`

Same payload shape as `/tools/chat`; response is streamed for long replies.

**Compatibility:** `POST /api/ai/v2/chat` and `POST /api/ai/v2/stream` remain registered as aliases (same handler).

**Notes:**
- v1 routes `POST /api/ai/ask` and `POST /api/ai/chat` remain **without** tool-calling.
- Tool execution is gated by registry permissions (not prompt-only).

---

## Chat (Session-based)
`POST /api/ai/chat`

**Request Body:**
```json
{
    "message": "What is the floor area of the first one?",
    "session_id": "uuid-123",
    "section": "units"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "The first unit (Unit #101) has a floor area of 120 sqm.",
        "session_id": "uuid-123"
    }
}
```

## Conversations
`GET /api/ai/conversations`

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "session_id": "uuid-123",
            "last_message": "The first unit...",
            "created_at": "2026-01-26T10:00:00Z"
        }
    ]
}
```

## AI calling

### Initiate call
`POST /api/ai/calls/initiate`

Optional header/body field `idempotency_key` avoids duplicate call rows for the same user.

### Unsupported customer target
If `target_type` is `customer`, the API returns **422** with:

```json
{
    "message": "Customer target is not supported for AI calling.",
    "error_code": "unsupported_customer_target"
}
```

Bulk: `POST /api/ai/calls/bulk` accepts optional `idempotency_key` (same behavior as initiate for the whole batch).

---

## Ads integrations

Supported advertising platforms in this codebase: **Meta**, **TikTok**, and **Snap** only.  
`GET /api/ads/accounts` includes per-account `capabilities` (campaign sync, insights, leads, conversions, `lead_source`).
