## Realtime Voice Decision Gate Report

### Final gate

- Ready for Realtime? `No`

### Why

- The non-realtime voice fallback MVP is implemented and materially tested.
- Policy consistency hardening is implemented and materially tested.
- Draft-only assistant form fill is implemented and materially tested.
- Budget and cost controls exist for the current non-realtime assistant path.
- Audit coverage for the current fallback flow is meaningfully present.

- But realtime voice readiness is still not achieved because the project does not yet have:
  - a realtime voice session broker
  - a realtime turn/interruption model
  - frontend microphone UX implementation for live capture
  - frontend playback/interruption handling for live responses
  - websocket/session-specific rate limiting and cost controls for long-lived audio sessions
  - audit semantics for partial turns, interruption, retries, reconnects, or concurrent live states

## Evidence by gate criterion

### 1) Audio fallback MVP

- `Implemented and usable`
- Evidence:
  - `routes/api.php` exposes `POST /api/ai/voice/chat` under `auth:sanctum`, `throttle:ai-assistant`, `ai.assistant`, `ai.redact`.
  - `app/Services/AI/Voice/VoiceAssistantService.php` executes:
    - audio upload
    - transcription
    - transcript redaction
    - existing `AIAssistantService::chat()`
    - optional TTS
  - `app/Http/Requests/AI/VoiceChatRequest.php` enforces upload size, mime allowlist, optional TTS allowlists, optional shared `session_id`.
  - `tests/Feature/AI/VoiceAssistantControllerTest.php` proves:
    - invalid audio rejection
    - transcription failure
    - fallback to text
    - session continuity
    - TTS success/failure handling
    - budget exhaustion before provider call
    - no raw PII leakage in audited fallback flow

- Gate judgment:
  - sufficient for fallback MVP
  - insufficient as proof of realtime readiness

### 2) Policy consistency hardening

- `Implemented and usable`
- Evidence:
  - `tests/Feature/AI/AiPolicyConsistencyHardProofTest.php`
  - `tests/Feature/AI/AiRouteHardeningTest.php`
  - `tests/Feature/AI/AIAssistantStreamingTest.php`
  - `tests/Unit/AI/ToolRegistryGatesTest.php`

- What is proven now:
  - consistent assistant permission refusal across `ask`, `chat`, `tools/chat`, `tools/stream`, `assistant/chat`
  - redaction reaches `chat`, `tools/*`, and `assistant/chat`
  - tool execution metadata is not leaked in current tool responses
  - stream path now respects orchestrator/tool mode

- Gate judgment:
  - good baseline for any future expansion
  - not enough by itself for realtime transport semantics

### 3) Draft-only assistant form fill

- `Implemented and usable`
- Evidence:
  - `app/Services/AI/Drafts/AssistantDraftService.php`
  - `app/Services/AI/SafeWrites/`
  - `tests/Feature/AI/AssistantDraftPreparationTest.php`
  - `tests/Feature/AI/SafeWriteActionSkeletonTest.php`

- What is proven now:
  - no auto-submit
  - no hidden business writes in covered draft/safe-write flows
  - ambiguity and missing-fields handling exist
  - confirmation boundary is explicit

- Gate judgment:
  - helpful for assistant safety
  - not a realtime readiness signal except indirectly through policy discipline

### 4) Real budget/cost controls

- `Implemented partially for non-realtime only`
- Evidence:
  - `config/ai_assistant.php` defines finite daily token budgets
  - `AIAssistantService::ensureWithinBudget()` is used by text chat
  - `AssistantDraftService::ensureWithinBudget()` is used by draft understanding
  - `tests/Feature/AI/AiBudgetGuardrailsTest.php`
  - `tests/Feature/AI/VoiceAssistantControllerTest.php` proves voice request returns `429` before provider call when assistant budget is exhausted
  - `AiOpenAiGateway` adds provider-side retries, circuit breaker hooks, and smart rate-limit checks

- What is missing for realtime:
  - no realtime session duration budget
  - no per-connection/per-stream rate model
  - no live token/audio-second metering contract
  - no backpressure policy for sustained microphone streaming

- Gate judgment:
  - current controls are real
  - they are not sufficient for realtime voice operations

### 5) Audit coverage richness

- `Implemented partially`
- Evidence:
  - `AiAuditService`
  - `app/Services/AI/Voice/VoiceAssistantService.php`
  - current fallback audit actions:
    - `voice_audio_received`
    - `voice_transcript_generated`
    - `voice_transcription_failed_fallback_text`
    - `voice_assistant_response_generated`
    - `voice_tts_generated`
    - `voice_tts_failed`
  - `tests/Feature/AI/VoiceAssistantControllerTest.php` proves correlation continuity and redacted summaries in the covered path

- What is missing for realtime:
  - partial transcript audit
  - interruption audit
  - reconnect / dropped-connection audit
  - live turn state transitions
  - concurrent audio/tool overlap audit
  - broker/session lifecycle audit

- Gate judgment:
  - good enough for push-to-talk fallback
  - not rich enough for realtime voice

### 6) Frontend readiness

- Mic UX: `Not implemented`
- Session handling: `Partial only`
- Playback handling: `Partial contract only`
- Interruption model: `Missing by design`

- Evidence:
  - `docs/phase-4-voice-ux-contract.md` defines only non-realtime fallback response states.
  - `resources/js/bootstrap.js` configures Echo/Reverb generically, but does not implement any voice UI.
  - `resources/js/chat-example.js` is a generic websocket chat example, not a voice client.
  - search across `resources/` shows no `getUserMedia`, `MediaRecorder`, live microphone capture, playback queue, or interruption control implementation.

- Gate judgment:
  - frontend is not ready for realtime voice

### 7) Backend readiness

- Realtime session broker: `Missing`
- Auth model: `Partial only`
- Rate limiting: `Partial only`
- Error handling: `Partial only`

- Evidence:
  - `config/reverb.php` and `config/broadcasting.php` show websocket infrastructure exists in the project.
  - `routes/api.php` includes `Broadcast::routes(['middleware' => ['auth:sanctum']]);`
  - existing broadcasting is used for generic chat/notifications, not voice-session orchestration.
  - no code surface was found for:
    - realtime voice broker
    - live audio chunk ingestion
    - partial turn buffering
    - interruption arbitration
    - concurrent turn cancellation
    - websocket voice-specific auth scope
    - realtime-specific error taxonomy

- Gate judgment:
  - websocket infrastructure existing is not the same as realtime voice readiness
  - backend is not ready for realtime voice

## What remains unfinished

- dedicated realtime voice architecture separate from the fallback endpoint
- realtime session broker and state machine
- live auth/token lifecycle for voice channels
- microphone capture UX
- live playback UX
- explicit interruption/barge-in model
- reconnect and retry semantics
- per-session live budget model
- live cost telemetry
- realtime-specific audit model
- tool safety policy for overlapping or interrupted live turns
- load and failure testing under long-lived connections

## What must not be skipped

- Do not retrofit realtime semantics into `POST /api/ai/voice/chat`.
- Do not treat Reverb presence as readiness proof.
- Do not skip a dedicated interruption model.
- Do not skip realtime-specific audit design.
- Do not skip websocket/session-specific rate limiting.
- Do not skip live budget/cost metering.
- Do not skip frontend implementation for:
  - mic permission flow
  - recording state
  - reconnect state
  - playback queue/state
  - explicit interruption behavior
- Do not skip concurrency rules between live turns and tool execution.
- Do not skip production-like latency/failure measurements against the existing fallback MVP.

## Strict decision

- Ready for Realtime? `No`
- Reason:
  - The project is ready for a strong non-realtime voice fallback baseline.
  - It is not yet ready for realtime voice because the required frontend interaction model and backend session/broker semantics do not exist in the codebase.
