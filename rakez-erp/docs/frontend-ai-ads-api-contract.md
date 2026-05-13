# Frontend AI + Ads API Contract

## Contract Status
- `Verified in code`: exact routes and behaviors backed by controllers, requests, and tests.
- `Proposed`: requested target contract not currently implemented as a verified Laravel API route.

## Auth

### Verified in code
- `POST /api/login`
  - Controller: `App\Http\Controllers\Registration\LoginController::login`
  - Request: `App\Http\Requests\registartion\LoginRequest`
  - Response:
    ```json
    {
      "access_token": "token",
      "user": {}
    }
    ```
- `GET /api/user`
  - Route closure in `routes/api.php`
- `POST /api/logout`
  - Controller: `App\Http\Controllers\Registration\LoginController::logout`

### Frontend requirement
- Send `Authorization: Bearer {{token}}` to all protected endpoints.

## Error Schema

### Verified patterns
- Validation errors generally return Laravel `422` with `errors`.
- AI custom failures often return:
  ```json
  {
    "success": false,
    "error_code": "code",
    "message": "Human-readable message"
  }
  ```
- Some Ads controllers return plain controller-shaped JSON without `success`.

### Recommended normalized error schema
```json
{
  "success": false,
  "error": {
    "code": "validation_error",
    "message": "Human-readable message",
    "details": {}
  }
}
```

## Pagination Schema

### Verified patterns
- AI conversations:
  ```json
  {
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 1
    }
  }
  ```
- Ads lists often return Laravel paginator JSON directly.

### Recommended normalized pagination envelope
```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 100
  }
}
```

## AI Assistant

### Verified in code: Legacy chat
- `POST /api/ai/chat`
  - Controller: `App\Http\Controllers\AI\AIAssistantController::chat`
  - Request: `App\Http\Requests\AI\ChatRequest`
  - Supports SSE when `stream=true`
  - Verified response envelope:
    ```json
    {
      "success": true,
      "data": {
        "message": "assistant reply",
        "session_id": "uuid",
        "conversation_id": 1,
        "suggestions": [],
        "links": [],
        "sources": [],
        "meta": {
          "session_id": "uuid",
          "section": "sales",
          "tokens": 100,
          "latency_ms": 1000,
          "model": "gpt-4.1-mini",
          "request_id": "req_x",
          "correlation_id": "uuid"
        }
      }
    }
    ```

### Verified in code: Tool-aware chat
- `POST /api/ai/tools/chat`
  - Controller: `App\Http\Controllers\AI\AiV2Controller::chat`
  - Verified response envelope:
    ```json
    {
      "success": true,
      "data": {
        "answer_markdown": "answer",
        "confidence": "high",
        "sources": [],
        "links": [],
        "suggested_actions": [],
        "follow_up_questions": [],
        "access_notes": {
          "had_denied_request": false,
          "reason": ""
        }
      }
    }
    ```
- `POST /api/ai/tools/stream`
  - SSE wrapper for the same orchestration path.

### Verified in code: Knowledge-base assistant
- `POST /api/ai/assistant/chat`
  - Controller: `App\Http\Controllers\AI\AssistantChatController::chat`
  - Request: `App\Http\Requests\AI\AssistantChatRequest`
  - Verified response:
    ```json
    {
      "success": true,
      "data": {
        "conversation_id": 1,
        "reply": "answer",
        "knowledge_used_count": 3
      }
    }
    ```

### Verified in code: Conversations and sections
- `GET /api/ai/conversations`
- `DELETE /api/ai/conversations/{sessionId}`
- `GET /api/ai/sections`

### Voice-compatible request shape

#### Proposed frontend payload
```json
{
  "message": "Frontend-transcribed voice text goes here",
  "conversation_id": "{{ai_conversation_id}}",
  "locale": "ar",
  "input_mode": "voice",
  "context": {
    "module": "sales"
  }
}
```

#### Reality today
- `POST /api/ai/chat` can accept the text portion, but `locale`, `input_mode`, and the exact target `context` contract are not formally validated in `App\Http\Requests\AI\ChatRequest`.
- Treat this as frontend-compatible but backend-incomplete.

### AI tool proposal response

#### Proposed, not implemented
```json
{
  "success": true,
  "data": {
    "conversation_id": "uuid",
    "answer": "Human-readable AI response",
    "tool_calls": [],
    "proposals": [
      {
        "proposal_id": "uuid",
        "action": "lead.assign",
        "summary": "Assign lead to sales agent",
        "requires_confirmation": true,
        "risk_level": "medium",
        "payload_preview": {},
        "expires_at": "ISO_DATE"
      }
    ]
  }
}
```

### AI tool execution confirmation

#### Proposed, not implemented
- Recommended route: `POST /api/ai/tools/confirm`
- Recommended payload:
  ```json
  {
    "proposal_id": "{{proposal_id}}",
    "confirm": true,
    "idempotency_key": "{{idempotency_key}}"
  }
  ```

### AI report generation

#### Verified in code
- `GET /api/ai/calls/analytics`
- `GET /api/ai/calls`
- `GET /api/ai/calls/{id}/transcript`

#### Proposed, not implemented
- Dedicated AI assistant report-generation endpoint for conversation/report artifacts.

## Ads

### Verified in code: Accounts
- `GET /api/ads/accounts`
- `POST /api/ads/accounts`
- `PATCH /api/ads/accounts/{id}`
- `POST /api/ads/accounts/{id}/refresh`
- `POST /api/ads/accounts/{id}/test`

### Verified in code: Campaigns and insights
- `GET /api/ads/campaigns`
- `GET /api/ads/adsets`
- `GET /api/ads/ads`
- `GET /api/ads/insights`
- `POST /api/ads/sync`
- `GET /api/ads/reports/platform-performance`
- `GET /api/ads/reports/campaign-performance`
- `GET /api/ads/reports/daily-trend`

### Verified in code: Leads and exports
- `GET /api/ads/leads`
- `GET /api/ads/leads/stored`
- `GET /api/ads/leads/export`
- `POST /api/ads/leads/export-snap`
- `POST /api/ads/leads/sync`
- `GET /api/ads/exports`
- `POST /api/ads/exports/leads`
- `GET /api/ads/exports/{id}`
- `GET /api/ads/exports/{id}/download`

### Verified in code: Outcomes and operations
- `POST /api/ads/outcomes`
- `GET /api/ads/outcomes/status`
- `GET /api/ads/ops/sync-runs`

### Ads webhook notes

#### Verified in code
- No Ads webhook API routes were found in `routes/api.php`.

#### Proposed
- `POST /api/ads/webhooks/meta/leads`
- `POST /api/ads/webhooks/tiktok/leads`
- `POST /api/ads/webhooks/snap/leads`
- `POST /api/ads/webhooks/google/offline-conversions`

## Localization Expectations

### Verified in code
- `App\Http\Requests\AI\AssistantChatRequest` supports `language` `ar|en`.
- `App\Services\AI\SystemPromptBuilder` instructs same-language responses.
- `App\Services\AI\RakizAiOrchestrator` contains Arabic-first instructions.

### Recommended
- Normalize on a top-level `locale` field for frontend requests.
- Support `ar` and `en` consistently across all AI endpoints.

## Frontend Guidance
1. Use `POST /api/ai/tools/chat` as the preferred future-facing AI route if you need tool-aware answers.
2. Use `POST /api/ai/chat` only for backward-compatible simple chat.
3. Treat all write-action confirmations as proposed only until a dedicated confirmation endpoint exists.
4. Use Ads account/reporting/leads routes as verified existing surfaces.
5. Treat Ads OAuth connect and inbound Ads webhooks as backend work not yet implemented.
