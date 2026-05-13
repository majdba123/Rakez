# AI + Ads Integration Audit

## Scope
This audit is limited to the Laravel API backend in this repository. No frontend runtime behavior, no deployment assumptions, and no production-readiness claims are made unless directly supported by code.

## Method
- Verified route inventory from `routes/api.php` and `php artisan route:list --path=ai --json`, `php artisan route:list --path=ads --json`.
- Verified controllers, services, providers, middleware, models, migrations, and tests under `app/`, `config/`, `database/migrations/`, and `tests/Feature/AI`, `tests/Feature/Ads`.
- Verified package-level integration support from `composer.json`.

## Executive Summary
The backend already contains substantial AI and Ads infrastructure, but it is not yet a fully agentic action-executing architecture. The current AI stack is split across three overlapping API patterns, the orchestrated tool path is read-heavy and advisory, and there is no confirmed human-approval execution pipeline for ERP mutations. Ads support is materially stronger than expected for Meta, Snap, and TikTok, with persisted accounts, insights, leads, exports, sync runs, and outcome outbox rows, but OAuth connect flows and inbound Ads webhooks are not implemented as API endpoints.

## Verified In Code

### 1. AI route surfaces
- Legacy AI assistant routes exist in `routes/api.php` using `App\Http\Controllers\AI\AIAssistantController::ask`, `chat`, `conversations`, `deleteSession`, `sections`.
  - `POST /api/ai/ask`
  - `POST /api/ai/chat`
  - `GET /api/ai/conversations`
  - `DELETE /api/ai/conversations/{sessionId}`
  - `GET /api/ai/sections`
- Tool-orchestrated routes exist in `routes/api.php` using `App\Http\Controllers\AI\AiV2Controller::chat`, `stream`.
  - `POST /api/ai/tools/chat`
  - `POST /api/ai/tools/stream`
  - `POST /api/ai/v2/chat`
  - `POST /api/ai/v2/stream`
- Knowledge-base assistant route exists in `routes/api.php` using `App\Http\Controllers\AI\AssistantChatController::chat`.
  - `POST /api/ai/assistant/chat`
- RAG document routes exist in `routes/api.php` using `App\Http\Controllers\AI\DocumentController`.
  - `POST /api/ai/documents`
  - `GET /api/ai/documents`
  - `GET /api/ai/documents/{id}`
  - `DELETE /api/ai/documents/{id}`
  - `POST /api/ai/documents/{id}/reindex`
  - `POST /api/ai/documents/search`
- AI calling routes exist in `routes/api.php` using `App\Http\Controllers\AI\AiCallController`.
  - `GET /api/ai/calls`
  - `GET /api/ai/calls/analytics`
  - `GET|POST|PUT|DELETE /api/ai/calls/scripts...`
  - `POST /api/ai/calls/initiate`
  - `POST /api/ai/calls/bulk`
  - `GET /api/ai/calls/{id}`
  - `GET /api/ai/calls/{id}/transcript`
  - `POST /api/ai/calls/{id}/retry`
- Twilio voice webhooks exist in `routes/api.php` using `App\Http\Controllers\AI\TwilioWebhookController`.
  - `POST /api/webhooks/twilio/voice/{callId}`
  - `POST /api/webhooks/twilio/gather/{callId}`
  - `POST /api/webhooks/twilio/status/{callId}`
  - `POST /api/webhooks/twilio/fallback/{callId}`

### 2. AI architecture split
- `App\Http\Controllers\AI\AIAssistantController` delegates to `App\Services\AI\AIAssistantService`.
- `App\Http\Controllers\AI\AiV2Controller` delegates to `App\Services\AI\RakizAiOrchestrator` and `App\Services\AI\Policy\RakizAiPolicyContextBuilder`.
- `App\Http\Controllers\AI\AssistantChatController` delegates to `App\Services\AI\AssistantLLMService`, `PromptVersionManager`, and `AiAuditService`.
- This means the repository currently has three AI API contracts, not one unified contract.

### 3. AI is partially agentic, not fully agentic
- `App\Services\AI\RakizAiOrchestrator` defines function tools and loops over tool calls.
- `App\Services\AI\ToolRegistry` registers read/advisory tools such as:
  - `tool_search_records`
  - `tool_get_lead_summary`
  - `tool_get_project_summary`
  - `tool_get_contract_status`
  - `tool_kpi_sales`
  - `tool_rag_search`
  - `tool_campaign_advisor`
  - `tool_finance_calculator`
  - `tool_marketing_analytics`
  - `tool_sales_advisor`
  - `tool_ai_call_status`
- No tool in `App\Services\AI\ToolRegistry` executes ERP write actions such as create/update/approve/assign/confirm.
- `App\Services\AI\RakizAiOrchestrator::getOutputJsonSchema()` returns `suggested_actions`, but not proposal IDs, execution receipts, or approval tokens.
- There is no verified endpoint for proposal confirmation or tool execution approval.

### 4. ERP grounding exists, but mostly through safe read/advisory surfaces
- `App\Services\AI\Tools\SearchRecordsTool` queries leads, contracts/projects, marketing tasks, and customers with per-user scoping.
- `App\Services\AI\Tools\GetLeadSummaryTool` enforces `leads.view` and `leads.view_all` checks before returning lead details.
- `App\Services\AI\Tools\RagSearchTool` uses `App\Services\AI\Rag\VectorSearchService`.
- `App\Http\Controllers\AI\DocumentController` restricts non-admin document access to `uploaded_by_user_id`.
- `App\Services\AI\ContextBuilder` and `App\Services\AI\ContextValidator` are used by `AIAssistantService` to build section-scoped context.

### 5. Permissions and policy gates are present
- All AI API routes require `auth:sanctum` in `routes/api.php`.
- AI tool routes are additionally gated in code by `User::can('use-ai-assistant')` in `App\Http\Controllers\AI\AiV2Controller`.
- Tool allowlisting is enforced by `App\Services\AI\ToolRegistry::allowedToolNamesForUser()` using `config/ai_assistant.php` `v2.tool_gates`.
- A deterministic early policy gate exists in `App\Services\AI\Policy\RakizAiPolicyContextBuilder`.
- Admin bypass exists in `App\Providers\AppServiceProvider::boot()` via `Gate::before(...)`.

### 6. Audit logging exists
- Structured audit trail table exists in `database/migrations/2026_03_17_000002_create_ai_audit_trail_table.php`.
- Correlation IDs were added in `database/migrations/2026_03_30_120000_add_correlation_id_to_ai_logs_and_audit.php`.
- `App\Services\AI\AiAuditService` writes entries to `App\Models\AiAuditEntry`.
- `App\Listeners\AI\LogAiInteraction` writes request metrics to `App\Models\AiInteractionLog`.
- `App\Listeners\AI\LogAiRequestFailed` logs failed requests.
- `App\Listeners\AI\LogAiToolExecutedAudit` logs tool-call audits.

### 7. Streaming exists
- `App\Http\Controllers\AI\AIAssistantController::chat()` supports `stream=true` and emits SSE through `streamChat()`.
- `App\Http\Controllers\AI\AiV2Controller::stream()` exposes SSE for the tool-orchestrated path.
- Streaming behavior is covered by `tests/Feature/AI/AIAssistantStreamingTest.php`.

### 8. Voice compatibility is only partial
- Frontend-transcribed text can be sent to existing chat endpoints because the APIs fundamentally consume text messages.
- `App\Http\Requests\AI\ChatRequest` does not validate `locale`, `input_mode`, or the requested `context.module/entity_type/entity_id` shape from the target contract.
- `App\Http\Requests\AI\AssistantChatRequest` validates `language`, but not `input_mode`.
- `database/migrations/2026_04_14_000001_add_voice_fields_to_messages_table.php` adds `type`, `voice_path`, and `voice_duration_seconds` to the general `messages` table, not to AI conversation tables.
- Conclusion: voice-transcribed text is feasible by convention, but not yet formalized as a stable AI API contract.

### 9. PII redaction exists but is not wired to AI routes
- `App\Http\Middleware\RedactPiiFromAi` redacts Saudi national IDs, phones, IBANs, and emails.
- `App\Http\Kernel` does not register an alias for this middleware.
- `routes/api.php` does not attach the middleware to AI routes.
- Tests exist for the middleware in `tests/Feature/AI/PiiRedactionMiddlewareTest.php` and `tests/Unit/AI/Middleware/RedactPiiTest.php`.

### 10. Ads integrations present now
- `composer.json` verifies installed support for:
  - OpenAI via `openai-php/laravel`
  - Meta via `facebook/php-business-sdk`
  - TikTok via `promopult/tiktok-marketing-api`
  - Twilio via `twilio/sdk`
- `config/ads_platforms.php` defines provider config for Meta, Snap, and TikTok.
- Persisted Ads infrastructure exists:
  - accounts: `ads_platform_accounts`
  - campaigns/ad sets/ads: `ads_campaigns`, `ads_ad_sets`, `ads_ads`
  - insights: `ads_insight_rows`
  - outcomes: `ads_outcome_events`
  - leads: `ads_lead_submissions`
  - exports: `ads_exports`
  - sync runs: `ads_sync_runs`
- Controllers verify exact ads API surfaces:
  - `App\Http\Controllers\Ads\AdsAccountsController`
  - `AdsInsightsController`
  - `AdsLeadsController`
  - `AdsExportsController`
  - `AdsReportingController`
  - `AdsOutcomeController`
  - `AdsOpsController`

### 11. Ads security and token handling are reasonably grounded
- Credentials are loaded from `.env` through `config/ads_platforms.php` and `.env.example`.
- `App\Infrastructure\Ads\Persistence\Models\AdsPlatformAccount` casts `access_token` and `refresh_token` as `encrypted`.
- `App\Infrastructure\Ads\Persistence\EloquentTokenStore` refreshes Snap and TikTok tokens and falls back to Meta system-user token behavior.
- `App\Infrastructure\Ads\BaseAdsClient` retries 429 and 5xx responses with backoff and jitter.
- `App\Infrastructure\Ads\Persistence\EloquentOutcomeStore` implements outbox retries, `next_attempt_at`, and dead-lettering.
- Outcome idempotency is enforced by unique key `ads_outcome_events_idempotency` in `database/migrations/2026_02_24_100004_create_ads_outcome_events_table.php`.

### 12. Ads route surfaces verified
- Accounts
  - `GET /api/ads/accounts`
  - `POST /api/ads/accounts`
  - `PATCH /api/ads/accounts/{id}`
  - `POST /api/ads/accounts/{id}/refresh`
  - `POST /api/ads/accounts/{id}/test`
- Campaign structure and insights
  - `GET /api/ads/campaigns`
  - `GET /api/ads/adsets`
  - `GET /api/ads/ads`
  - `GET /api/ads/insights`
  - `POST /api/ads/sync`
- Leads and exports
  - `GET /api/ads/leads`
  - `GET /api/ads/leads/stored`
  - `GET /api/ads/leads/export`
  - `POST /api/ads/leads/export-snap`
  - `POST /api/ads/leads/sync`
  - `GET /api/ads/exports`
  - `POST /api/ads/exports/leads`
  - `GET /api/ads/exports/{id}`
  - `GET /api/ads/exports/{id}/download`
- Reporting and operations
  - `GET /api/ads/reports/platform-performance`
  - `GET /api/ads/reports/campaign-performance`
  - `GET /api/ads/reports/daily-trend`
  - `GET /api/ads/ops/sync-runs`
- Outcomes
  - `POST /api/ads/outcomes`
  - `GET /api/ads/outcomes/status`

## Verified Missing Or Incomplete
- No verified ads webhook endpoints for Meta lead webhooks, TikTok lead callbacks, Snap lead callbacks, or Google Ads offline conversion callbacks.
- No verified OAuth redirect/callback endpoints for Ads account connection.
- No verified Google Ads or YouTube Ads backend integration.
- No verified LinkedIn Ads backend integration.
- No verified SerpAPI or market-research provider integration.
- No verified Anthropic, Ollama, or local-LLM adapter implementation.
- No verified AI proposal table, proposal expiry storage, human confirmation endpoint, or idempotent execution contract for agentic ERP writes.
- No verified unified localization contract for `locale` across all AI endpoints.
- No verified AI conversation-history endpoint for the `/api/ai/assistant/chat` knowledge-base conversation model.

## Inferred
- The intended direction is an ERP-grounded AI assistant with tool calling, RAG, and AI calling, but current delivery is still transitional because multiple AI contracts coexist.
- `App\Services\AI\RakizAiOrchestrator` is positioned as the future primary contract for frontend tool-aware chat, but the absence of write-action execution keeps it in a safe advisory phase.
- Ads outcomes are designed as an outbox for downstream conversion APIs, which is a sound base for scalable server-side conversions.

## Recommended
1. Consolidate frontend AI traffic onto one primary contract, preferably the orchestrated path, and deprecate overlapping chat variants gradually.
2. Introduce a first-class proposal/execution layer for ERP writes with:
   - proposal table
   - proposal expiry
   - confirmation endpoint
   - idempotency key
   - execution receipt
3. Keep current AI tools read-only until proposal/execution controls exist.
4. Wire `RedactPiiFromAi` onto all AI request entry points or an AI-specific middleware group.
5. Add explicit request support for `locale`, `input_mode`, and structured `context` on the primary chat endpoint.
6. Add Ads OAuth connect/callback routes instead of manual token upsert only.
7. Add Ads webhook receivers per platform with signature validation and replay protection.
8. Add Google/YouTube and LinkedIn only after the account, campaign, webhook, and outcome abstractions are generalized beyond the current Meta/Snap/TikTok assumptions.
9. Expose a documented error schema and pagination schema consistently across AI and Ads endpoints.
10. Add a separate AI interaction-report endpoint only if the frontend needs analytics dashboards; do not overload chat endpoints for reporting.
