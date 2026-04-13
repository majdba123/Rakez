## Phase 5 Hard-Proof QA

This phase is the rollout gate for the non-realtime assistant stack.

### Scope under proof

- text assistant
- SSE streaming
- tool orchestration
- draft-only assistant form fill
- safe-write skeleton
- voice fallback
- optional TTS

### Required test groups

- Route hardening and auth
  - `tests/Feature/AI/AiApiUnauthenticatedTest.php`
  - `tests/Feature/AI/AiRouteHardeningTest.php`
- Validation and controller contract
  - `tests/Feature/AI/AIAssistantControllerTest.php`
  - `tests/Feature/AI/AIAssistantStreamingTest.php`
  - `tests/Feature/AI/AiPolicyConsistencyHardProofTest.php`
- PII and budget guardrails
  - `tests/Feature/AI/PiiRedactionMiddlewareTest.php`
  - `tests/Feature/AI/AiBudgetGuardrailsTest.php`
- Tool gating
  - `tests/Unit/AI/ToolRegistryGatesTest.php`
  - `tests/Feature/AI/AllToolsTest.php`
- Draft-only form fill
  - `tests/Feature/AI/AssistantDraftPreparationTest.php`
  - `tests/Feature/AI/SafeWriteActionSkeletonTest.php`
- Voice fallback
  - `tests/Feature/AI/VoiceAssistantControllerTest.php`

### Evidence required before rollout

- all required suites pass on the target branch
- no auth regression on any `/api/ai/*` route
- draft and safe-write routes are included in the auth/middleware regression surface, not treated as implied coverage
- no path from AI to unsafe writes
- no voice route bypass around `ai.assistant` or `ai.redact`
- sync and stream assistant behavior remain policy-consistent

### Explicit fail conditions

- any unauthorized assistant route returns non-401/403 behavior
- any draft flow writes to business tables
- any tool becomes exposed without explicit gate coverage
- TTS failure suppresses the text answer
- voice path stores permanent audio as part of the MVP

### Suggested command batch

```bash
php artisan test --filter="(AiApiUnauthenticatedTest|AiRouteHardeningTest|AiBudgetGuardrailsTest|PiiRedactionMiddlewareTest|AIAssistantControllerTest|AIAssistantStreamingTest|AiPolicyConsistencyHardProofTest|ToolRegistryGatesTest|AllToolsTest|AssistantDraftPreparationTest|SafeWriteActionSkeletonTest|VoiceAssistantControllerTest)"
```
