## Phase 4 Voice UX Contract

Backend source of truth:

- `docs/backend-ai-voice-chat-contract.md`

This file remains a frontend UX summary for the non-realtime voice fallback path.

### Route

- `POST /api/ai/voice/chat`

### Response states

- `data.transcript.source = audio`
  - Transcription succeeded from uploaded audio.
- `data.transcript.source = fallback_text`
  - Audio transcription failed and the server safely used the caller-provided fallback text.

- `data.speech.requested = false`
  - Text response only. No playback requested.
- `data.speech.requested = true` and `data.speech.generated = true`
  - Playback asset is available in `data.speech.audio`.
- `data.speech.requested = true` and `data.speech.generated = false`
  - Text response is still authoritative; playback failed and `data.speech.error_code` explains why.

### Frontend requirements

- Always render the assistant text first.
- Never block the text response on TTS.
- Surface transcript provenance:
  - `audio` vs `fallback_text`
- Surface TTS state explicitly:
  - `ready`
  - `failed`
  - `not_requested`
- Treat voice as single-turn push-to-talk.
- Do not simulate live or concurrent voice turns.

### Explicitly out of scope

- streaming playback
- partial transcript rendering
- interruption
- barge-in
- full duplex
- concurrent voice turns
