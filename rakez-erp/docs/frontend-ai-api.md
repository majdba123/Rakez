# Frontend AI API (Canonical Integration)

## Recommended Endpoint
Use only:
- `POST /api/ai/tools/chat`

Use streaming only when needed:
- `POST /api/ai/tools/stream`

## Legacy Endpoints (Backward Compatibility)
These are still available but should not be used for new frontend work:
- `POST /api/ai/chat`
- `POST /api/ai/ask`
- `POST /api/ai/assistant/chat`
- `POST /api/ai/v2/chat`

## Auth
- All AI routes require Sanctum auth.
- Send `Authorization: Bearer <token>`.

## Canonical Request
```json
{
  "message": "string|required",
  "conversation_id": "nullable|string",
  "locale": "nullable|in:ar,en",
  "input_mode": "nullable|in:text,voice",
  "context": {
    "module": "nullable|string",
    "entity_type": "nullable|string",
    "entity_id": "nullable"
  }
}
```

## Voice Support
- `input_mode=voice` is metadata only.
- Frontend must transcribe voice to text and send it in `message`.
- Backend processes voice and text the same way.

## Canonical Success Response
```json
{
  "success": true,
  "data": {
    "conversation_id": "string|null",
    "answer": "string",
    "answer_markdown": "string",
    "sources": [],
    "links": [],
    "suggested_actions": [],
    "follow_up_questions": [],
    "meta": {
      "locale": "ar|en",
      "input_mode": "text|voice",
      "model": "string|null",
      "tokens": null,
      "latency_ms": null,
      "correlation_id": "string|null"
    }
  }
}
```

## Canonical Error Response
```json
{
  "success": false,
  "error": {
    "code": "string",
    "message": "string",
    "details": {}
  }
}
```

## Permission Behavior
- Route requires authenticated user.
- Endpoint enforces `use-ai-assistant`.
- Tool execution remains permission-gated by existing tool registry.
- Current tools remain read-only/advisory.

## Notes
- Canonical endpoint accepts legacy compatibility fields (`session_id`, `page_context`, `section`) but frontend should prefer `conversation_id` and `context`.
- PII redaction middleware is applied to canonical tool routes.
