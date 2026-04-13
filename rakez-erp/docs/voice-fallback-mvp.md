## MVP Voice Architecture

This MVP adds in-app push-to-talk fallback voice on top of the existing text assistant. It explicitly does not add realtime transport, WebRTC, Twilio call handling, full duplex audio, interruption, or concurrent turns.

### Chosen architecture

- New endpoint: `POST /api/ai/voice/chat`
- Transport: regular authenticated multipart upload under `auth:sanctum`
- Input flow:
  - audio upload
  - transcription
  - PII redaction on transcript
  - existing `AIAssistantService::chat()`
  - optional TTS generation from the assistant text reply
- Fallback:
  - if transcription fails and `fallback_text` is provided, use `fallback_text`
  - if no `fallback_text` is provided, return the transcription error

### Why a dedicated endpoint was added

- Audio upload does not fit cleanly into the existing JSON `POST /api/ai/chat` contract.
- A dedicated endpoint keeps the existing text assistant unchanged for current clients.
- The voice adapter still reuses the same session model and chat service.

### Storage decision

- No persistent audio storage was added.
- The MVP uses Laravel/PHP temporary upload files only and forwards the temporary file handle to OpenAI transcription.
- No streaming upload path was added.

### Session continuity

- `session_id` remains optional and uses the same UUID semantics as text chat.
- The voice endpoint passes `session_id` directly into `AIAssistantService::chat()`.
- Voice-specific metadata is attached onto the latest user and assistant `AIConversation` rows for that session.
- This is safe enough for the MVP because concurrent turns are explicitly out of scope.

### Audit entries

The voice flow adds explicit audit actions on top of the assistant’s existing text audit:

- `voice_audio_received`
- `voice_transcript_generated`
- `voice_transcription_failed_fallback_text`
- `voice_assistant_response_generated`
- `voice_tts_generated`
- `voice_tts_failed`

## Files/Endpoints Added

### New files

- `config/ai_voice.php`
- `app/Http/Requests/AI/VoiceChatRequest.php`
- `app/Http/Controllers/AI/VoiceAssistantController.php`
- `app/Services/AI/Voice/VoiceAssistantService.php`
- `app/Services/AI/Voice/VoiceTranscriptionService.php`
- `app/Services/AI/Voice/VoiceSynthesisService.php`
- `tests/Feature/AI/VoiceAssistantControllerTest.php`

### Modified files

- `routes/api.php`
- `app/Services/AI/AiOpenAiGateway.php`
- `app/Http/Middleware/RedactPiiFromAi.php`
- `tests/Feature/AI/AiApiUnauthenticatedTest.php`
- `tests/Feature/AI/AiRouteHardeningTest.php`

### Endpoint contract

`POST /api/ai/voice/chat`

Accepted fields:

- `audio`: optional uploaded file, required when `fallback_text` is absent
- `fallback_text`: optional string, required when `audio` is absent
- `session_id`: optional UUID
- `section`: optional assistant section
- `context`: optional assistant context
- `with_tts`: optional boolean
- `tts_voice`: optional TTS voice
- `tts_format`: optional TTS format

## Validation, Security, Cost Controls

### Validation

- strict mime-type allowlist for short audio uploads
- strict max upload size
- transcript max length bounded to current text assistant expectations
- TTS voice/format allowlists
- existing section/context validation reused

### Security

- route stays under `auth:sanctum`
- route stays under `throttle:ai-assistant`
- route stays under `ai.assistant`
- route stays under `ai.redact`
- fallback text is redacted by middleware
- generated transcripts are redacted before being passed to the text assistant
- no persistent audio storage

### Cost controls

- same AI route throttle as text assistant
- same assistant budget guard for the text-response leg
- bounded upload size
- bounded transcript size
- optional TTS only, never forced
- no realtime sockets or long-lived streams

## Tests Added

- unauthenticated access
- invalid audio
- transcription failure without fallback text
- safe fallback to text when transcription fails
- session continuity across repeated voice turns
