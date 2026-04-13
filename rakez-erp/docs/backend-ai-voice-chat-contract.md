# Backend API Contract: `POST /api/ai/voice/chat`

This document freezes the backend-owned wire contract for the non-realtime voice fallback endpoint.

## Contract Status

### Implemented and usable

- `POST /api/ai/voice/chat`
- authenticated multipart upload under `auth:sanctum`
- shared AI access controls:
  - `throttle:ai-assistant`
  - `ai.assistant`
  - `ai.redact`
- audio upload
- transcription
- transcript redaction
- fallback-to-text when transcription fails and `fallback_text` exists
- reuse of existing text assistant session model
- optional TTS
- redacted audit trail
- budget rejection before provider call
- voice-specific backend kill switch via `config('ai_voice.enabled')`

### Partial

- `section` values are shared with the text assistant capability surface and are server-configured. This route validates them, but this document does not freeze a static enum list.
- route-throttle `429` and unauthenticated `401` are enforced, but their JSON body shape is framework-surfaced rather than explicitly owned by this controller.
- the route is still governed by the broader assistant enablement switch as well as the voice-specific switch; clients must treat both as operational availability gates.

### Broken or inconsistent

- Error envelopes are not fully uniform across all failure classes:
  - controller/service failures use `{ success, error_code, message }`
  - permission failures use `{ success, message }`
  - validation failures use the framework validation envelope
  - route throttle / unauthenticated failures are framework-surfaced
- Voice-disabled behavior is a controller/service error envelope with HTTP `503` and `error_code=ai_voice_disabled`.

Clients must therefore branch on HTTP status code first, then on the documented envelope for that status class.

### Explicitly out of scope

- realtime transport
- WebRTC
- interruption
- barge-in
- full duplex
- concurrent live turns
- partial transcript streaming

## Route

- Method: `POST`
- Path: `/api/ai/voice/chat`
- Content-Type: `multipart/form-data`
- Auth: `auth:sanctum`

## Request Schema

### Fields

- `audio`: optional uploaded file, required when `fallback_text` is absent
- `fallback_text`: optional string, required when `audio` is absent
- `session_id`: optional UUID
- `section`: optional string
- `context`: optional object
- `with_tts`: optional boolean
- `tts_voice`: optional string, allowed only when `with_tts=true`
- `tts_format`: optional string, allowed only when `with_tts=true`

### Validation Rules

- `audio`
  - must be a file
  - max size: `12288 KB` by default
  - allowed MIME types:
    - `audio/mpeg`
    - `audio/mp3`
    - `audio/mp4`
    - `audio/x-m4a`
    - `audio/wav`
    - `audio/x-wav`
    - `audio/webm`
    - `audio/ogg`
    - `video/webm`
  - allowed extensions:
    - `mp3`
    - `wav`
    - `m4a`
    - `mp4`
    - `webm`
    - `ogg`
- `fallback_text`
  - string
  - trimmed before validation/use
  - max length: `2000`
- `session_id`
  - must be a UUID when present
- `section`
  - must be one of the server-configured shared AI sections when present
- `context`
  - object
  - additional nested validation depends on the selected `section`
- `with_tts`
  - boolean
- `tts_voice`
  - allowed values:
    - `alloy`
    - `ash`
    - `ballad`
    - `coral`
    - `echo`
    - `sage`
    - `shimmer`
    - `verse`
- `tts_format`
  - allowed values:
    - `mp3`
    - `wav`
    - `opus`

### Request Semantics

- At least one of `audio` or `fallback_text` must be materially present.
- `fallback_text` is not a parallel second prompt.
- `fallback_text` is only used when:
  - the caller sent it, and
  - audio transcription failed.
- `with_tts=false` or omission means text-only response.
- Supplying `tts_voice` or `tts_format` without `with_tts=true` is a validation error.

### Example Requests

Text-only fallback-capable request:

```http
POST /api/ai/voice/chat
Content-Type: multipart/form-data

audio=@turn.wav
fallback_text=لو فشل التفريغ استخدم هذا النص
session_id=22222222-2222-2222-2222-222222222222
with_tts=false
```

TTS request:

```http
POST /api/ai/voice/chat
Content-Type: multipart/form-data

audio=@turn.wav
with_tts=true
tts_voice=alloy
tts_format=mp3
```

## Success Response Schema

HTTP `200 OK`

```json
{
  "success": true,
  "data": {
    "input": {
      "audio_uploaded": true,
      "audio_persisted": false,
      "fallback_text_provided": true,
      "fallback_text_used": false
    },
    "transcript": {
      "text": "string",
      "source": "audio",
      "language": "ar",
      "duration": 1.4,
      "fallback_text_used": false
    },
    "assistant": {
      "message": "string",
      "session_id": "uuid",
      "conversation_id": 123,
      "error_code": null,
      "meta": {
        "session_id": "uuid",
        "section": "general",
        "tokens": 123,
        "latency_ms": 456,
        "model": "string",
        "request_id": "string or null",
        "correlation_id": "uuid or string"
      },
      "authoritative": true
    },
    "speech": {
      "requested": false,
      "generated": false,
      "audio": null,
      "error_code": null
    }
  }
}
```

## Allowed Response States

Exactly these semantic states are supported for successful requests.

### State A: audio transcript, text only

- `data.transcript.source = "audio"`
- `data.speech.requested = false`
- `data.speech.generated = false`
- `data.speech.audio = null`
- assistant text is authoritative

### State B: fallback text used, text only

- `data.transcript.source = "fallback_text"`
- `data.transcript.fallback_text_used = true`
- `data.input.fallback_text_used = true`
- `data.speech.requested = false`
- assistant text is authoritative

### State C: audio transcript, TTS generated

- `data.transcript.source = "audio"`
- `data.speech.requested = true`
- `data.speech.generated = true`
- `data.speech.audio` is a populated object
- assistant text remains authoritative even when playback exists

### State D: audio transcript or fallback text, TTS failed safely

- `data.speech.requested = true`
- `data.speech.generated = false`
- `data.speech.audio = null`
- `data.speech.error_code` is populated
- assistant text remains authoritative

No other success-state semantics are part of the contract.

## Field Semantics

### `data.input`

- `audio_uploaded`
  - `true` when the request contained an uploaded audio file
  - `false` when the request relied only on `fallback_text`
- `audio_persisted`
  - always `false` for this MVP contract
  - means the backend does not permanently store the uploaded audio asset
- `fallback_text_provided`
  - `true` when caller sent non-empty `fallback_text`
- `fallback_text_used`
  - `true` only when transcription failed and the backend actually used the fallback text for the assistant turn

### `data.transcript`

- `text`
  - the sanitized text actually sent into the text assistant leg
- `source`
  - `"audio"` means transcription came from uploaded audio
  - `"fallback_text"` means transcription failed and fallback text was safely used
- `language`
  - transcription language from the provider when audio transcription succeeds
  - `null` when no transcription metadata exists
- `duration`
  - transcription duration from the provider when available
  - `null` when unavailable
- `fallback_text_used`
  - mirrors the actual transcript provenance decision

### `data.assistant`

- `message`
  - the assistant text reply
- `session_id`
  - authoritative session identifier for the turn
- `conversation_id`
  - persisted assistant conversation row id
- `error_code`
  - nullable soft warning from the reused text assistant leg
  - currently may be non-null on degraded success such as empty-model-reply fallback text generation
- `meta`
  - shared assistant execution metadata
- `authoritative`
  - always `true`
  - clients must always render/use this text as the source of truth

### `data.speech`

- `requested`
  - `false` means text-only path
  - `true` means caller requested TTS generation
- `generated`
  - `true` means a playback asset exists in `data.speech.audio`
  - `false` means no playback asset exists
- `audio`
  - object only when `generated=true`
  - schema:
    - `audio_base64`: base64 audio payload
    - `format`: one of `mp3|wav|opus`
    - `mime_type`: derived MIME type
    - `size_bytes`: payload size in bytes
    - `voice`: selected TTS voice
- `error_code`
  - populated only when `requested=true` and TTS generation failed safely

## Session Semantics

- `session_id` is optional on request.
- If omitted, the backend creates a new session and returns it in `data.assistant.session_id`.
- If provided and valid, the backend reuses that session for conversation continuity.
- The voice route shares the same conversation/session model as the text assistant.
- The returned `data.assistant.session_id` is authoritative and must be used for subsequent turns.
- Concurrent live turns are out of scope. This contract assumes single-turn request/response usage.

## TTS Semantics

- TTS is optional and explicitly opt-in.
- TTS generation happens after the assistant text reply is produced.
- TTS failure never suppresses or replaces the assistant text.
- The assistant text is always authoritative.
- `data.speech.requested = false` means no playback was requested.
- `data.speech.requested = true` and `generated = true` means playback asset exists.
- `data.speech.requested = true` and `generated = false` means text remains authoritative and playback failed safely.

## Budget and Rate Failure Semantics

### Backend budget exhaustion

- HTTP `429`
- controller-owned error envelope
- `error_code = "ai_budget_exceeded"`
- rejected before transcription/provider call

Example:

```json
{
  "success": false,
  "error_code": "ai_budget_exceeded",
  "message": "Daily token budget exceeded (100/100). Please try again later."
}
```

### AI gateway/provider rate limit

- HTTP `429`
- controller-owned error envelope when surfaced as `AiAssistantException`
- `error_code = "ai_rate_limited"`

Example:

```json
{
  "success": false,
  "error_code": "ai_rate_limited",
  "message": "AI provider rate limit reached."
}
```

### Route middleware throttle

- HTTP `429`
- enforced by `throttle:ai-assistant`
- clients must treat the HTTP status code as authoritative
- JSON body is framework-surfaced and not currently normalized by this controller

## Error Model

There is no single universal error envelope today. The contract is therefore status-class based.

### 401 unauthenticated

- source: `auth:sanctum`
- HTTP status is authoritative
- JSON body is framework-surfaced

### 403 forbidden

- source: `ai.assistant`
- stable envelope:

```json
{
  "success": false,
  "message": "You do not have permission to use the AI assistant."
}
```

### 422 validation

- source: request validation
- uses the framework validation envelope
- stable semantics:
  - `message`: summary string
  - `errors`: field-to-array-of-messages map

Example:

```json
{
  "message": "The tts voice field is invalid.",
  "errors": {
    "tts_voice": [
      "TTS voice can only be sent when with_tts is true."
    ]
  }
}
```

### 422 controller/service validation failure

- source: service-layer `AiAssistantException`
- stable envelope:

```json
{
  "success": false,
  "error_code": "ai_validation_failed",
  "message": "Uploaded audio is invalid."
}
```

### 429 controller/service failure

- source: budget/rate failures surfaced as `AiAssistantException`
- stable envelope:

```json
{
  "success": false,
  "error_code": "ai_budget_exceeded",
  "message": "Daily token budget exceeded (100/100). Please try again later."
}
```

### 503 provider unavailable

- stable envelope:

```json
{
  "success": false,
  "error_code": "ai_provider_unavailable",
  "message": "AI provider is temporarily unavailable."
}
```

## Success Payload Examples

### Example 1: audio transcript, no TTS

```json
{
  "success": true,
  "data": {
    "input": {
      "audio_uploaded": true,
      "audio_persisted": false,
      "fallback_text_provided": false,
      "fallback_text_used": false
    },
    "transcript": {
      "text": "اريد شرح نمط two pointers",
      "source": "audio",
      "language": "ar",
      "duration": 1.8,
      "fallback_text_used": false
    },
    "assistant": {
      "message": "هذا شرح مبسط لنمط two pointers...",
      "session_id": "22222222-2222-2222-2222-222222222222",
      "conversation_id": 91,
      "error_code": null,
      "meta": {
        "session_id": "22222222-2222-2222-2222-222222222222",
        "section": "general",
        "tokens": 321,
        "latency_ms": 812,
        "model": "gpt-4.1-mini",
        "request_id": "req_123",
        "correlation_id": "corr_123"
      },
      "authoritative": true
    },
    "speech": {
      "requested": false,
      "generated": false,
      "audio": null,
      "error_code": null
    }
  }
}
```

### Example 2: transcription failed, fallback text used

```json
{
  "success": true,
  "data": {
    "input": {
      "audio_uploaded": true,
      "audio_persisted": false,
      "fallback_text_provided": true,
      "fallback_text_used": true
    },
    "transcript": {
      "text": "Use this text instead",
      "source": "fallback_text",
      "language": null,
      "duration": null,
      "fallback_text_used": true
    },
    "assistant": {
      "message": "Fallback text answer",
      "session_id": "22222222-2222-2222-2222-222222222222",
      "conversation_id": 92,
      "error_code": null,
      "meta": {
        "session_id": "22222222-2222-2222-2222-222222222222",
        "section": null,
        "tokens": 210,
        "latency_ms": 640,
        "model": "gpt-4.1-mini",
        "request_id": "req_124",
        "correlation_id": "corr_124"
      },
      "authoritative": true
    },
    "speech": {
      "requested": false,
      "generated": false,
      "audio": null,
      "error_code": null
    }
  }
}
```

### Example 3: TTS generated

```json
{
  "success": true,
  "data": {
    "input": {
      "audio_uploaded": true,
      "audio_persisted": false,
      "fallback_text_provided": false,
      "fallback_text_used": false
    },
    "transcript": {
      "text": "Read this aloud",
      "source": "audio",
      "language": "ar",
      "duration": 1.4,
      "fallback_text_used": false
    },
    "assistant": {
      "message": "Spoken answer",
      "session_id": "33333333-3333-3333-3333-333333333333",
      "conversation_id": 93,
      "error_code": null,
      "meta": {
        "session_id": "33333333-3333-3333-3333-333333333333",
        "section": null,
        "tokens": 180,
        "latency_ms": 500,
        "model": "gpt-4.1-mini",
        "request_id": "req_125",
        "correlation_id": "corr_125"
      },
      "authoritative": true
    },
    "speech": {
      "requested": true,
      "generated": true,
      "audio": {
        "audio_base64": "ZmFrZS1hdWRpbw==",
        "format": "mp3",
        "mime_type": "audio/mpeg",
        "size_bytes": 10,
        "voice": "alloy"
      },
      "error_code": null
    }
  }
}
```

### Example 4: TTS failed safely

```json
{
  "success": true,
  "data": {
    "input": {
      "audio_uploaded": true,
      "audio_persisted": false,
      "fallback_text_provided": false,
      "fallback_text_used": false
    },
    "transcript": {
      "text": "Read this aloud",
      "source": "audio",
      "language": "ar",
      "duration": 1.4,
      "fallback_text_used": false
    },
    "assistant": {
      "message": "Text survives TTS failure",
      "session_id": "44444444-4444-4444-4444-444444444444",
      "conversation_id": 94,
      "error_code": null,
      "meta": {
        "session_id": "44444444-4444-4444-4444-444444444444",
        "section": null,
        "tokens": 176,
        "latency_ms": 520,
        "model": "gpt-4.1-mini",
        "request_id": "req_126",
        "correlation_id": "corr_126"
      },
      "authoritative": true
    },
    "speech": {
      "requested": true,
      "generated": false,
      "audio": null,
      "error_code": "ai_provider_unavailable"
    }
  }
}
```

## Failure Payload Examples

### Example: unauthenticated

Authoritative contract:

- status `401`

Current implementation:

```json
{
  "message": "Unauthenticated."
}
```

### Example: forbidden

```json
{
  "success": false,
  "message": "You do not have permission to use the AI assistant."
}
```

### Example: invalid audio mime

```json
{
  "message": "The audio field must be a file of type: audio/mpeg, audio/mp3, audio/mp4, audio/x-m4a, audio/wav, audio/x-wav, audio/webm, audio/ogg, video/webm.",
  "errors": {
    "audio": [
      "The audio field must be a file of type: audio/mpeg, audio/mp3, audio/mp4, audio/x-m4a, audio/wav, audio/x-wav, audio/webm, audio/ogg, video/webm."
    ]
  }
}
```

### Example: transcription failed and no fallback text exists

```json
{
  "success": false,
  "error_code": "ai_provider_unavailable",
  "message": "AI provider is temporarily unavailable."
}
```

### Example: route throttle

Authoritative contract:

- status `429`

Current framework-shaped body:

```json
{
  "message": "Too Many Attempts."
}
```

## Implementation Alignment Check

### Aligned

- request validation exists and matches the documented audio/fallback/TTS/session semantics
- response contains explicit transcript provenance
- response contains explicit TTS requested/generated state
- assistant text is always marked authoritative
- no permanent audio storage is represented in the payload
- budget exhaustion is rejected before transcription/provider call
- correlation continuity is preserved through audit and assistant response metadata
- transcript and audit summaries are redacted

### Known gaps in contract ownership

- `401` body shape is not explicitly owned by `VoiceAssistantController`
- route-throttle `429` body shape is not explicitly owned by `VoiceAssistantController`
- `section` enum is shared/server-configured rather than statically frozen here
- validation message strings come from framework validation and should be treated as human-readable, not machine-stable, except for field presence in `errors`

## Contract Regression Test Plan

These tests should remain mandatory for this endpoint.

### Request contract

- reject invalid MIME type
- reject oversize audio
- reject invalid `session_id`
- reject `tts_voice` / `tts_format` when `with_tts` is not true
- reject missing both `audio` and `fallback_text`
- accept audio-only request
- accept fallback-text-only request

### Success-state matrix

- audio transcript + no TTS
- transcription failure + fallback text used
- audio transcript + TTS generated
- audio transcript + TTS requested but generation failed safely

### Session semantics

- server generates session when omitted
- provided session is reused across repeated turns
- returned `assistant.session_id` is stable and authoritative

### Failure semantics

- unauthenticated `401`
- forbidden `403`
- validation `422`
- budget exhaustion `429` before transcription/provider call
- provider rate-limit `429`
- provider unavailable `503`
- route-throttle `429`

### Safety and audit semantics

- fallback text is redacted before service use
- transcript text is redacted before assistant reuse
- audit summaries contain no raw PII
- one correlation id spans voice audit entries and assistant response metadata

### Contract drift guard

- assert exact presence and type of:
  - `data.input.*`
  - `data.transcript.*`
  - `data.assistant.authoritative`
  - `data.speech.requested`
  - `data.speech.generated`
  - `data.speech.audio`
  - `data.speech.error_code`
