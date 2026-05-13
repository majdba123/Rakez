# Ads Platform Integration Plan

## Verified Current State

### Implemented platforms
- Meta
  - Config: `config/ads_platforms.php`
  - Client: `App\Infrastructure\Ads\Meta\MetaClient`
  - Lead reader: `MetaLeadGenReader`
  - Insights reader: `MetaInsightsReader`
  - Conversions writer: `MetaConversionsWriter`
  - CRM writer: `MetaCrmWriter`
- Snap
  - Config: `config/ads_platforms.php`
  - Client: `App\Infrastructure\Ads\Snap\SnapClient`
  - Lead reader: `SnapLeadGenReader`
  - Insights reader: `SnapInsightsReader`
  - Conversions writer: `SnapConversionsWriter`
- TikTok
  - Config: `config/ads_platforms.php`
  - Client: `App\Infrastructure\Ads\TikTok\TikTokClient`
  - Lead reader: `TikTokLeadGenReader`
  - Insights reader: `TikTokInsightsReader`
  - Events writer: `TikTokEventsWriter`

### Implemented persistence
- Accounts: `ads_platform_accounts`
- Structure: `ads_campaigns`, `ads_ad_sets`, `ads_ads`
- Insights: `ads_insight_rows`
- Leads: `ads_lead_submissions`
- Exports: `ads_exports`
- Outcome outbox: `ads_outcome_events`
- Ops tracking: `ads_sync_runs`

### Implemented API surfaces
- Accounts, sync, reports, leads, stored leads, exports, outbox status, and manual outcome enqueueing all exist under `/api/ads/...`.

## Missing Or Not Verified
- Meta OAuth redirect/callback endpoints.
- Snap OAuth redirect/callback endpoints.
- TikTok OAuth redirect/callback endpoints.
- Meta inbound lead webhook.
- Snap inbound lead webhook.
- TikTok inbound lead webhook.
- Google Ads or YouTube Ads integration.
- LinkedIn Ads integration.
- SerpAPI or market research provider integration.

## Credentials Policy

### Verified in code
- Provider credentials are read from `.env` via `config/ads_platforms.php`.
- `.env.example` includes placeholders for:
  - `META_APP_ID`
  - `META_APP_SECRET`
  - `META_ADS_ACCESS_TOKEN`
  - `META_AD_ACCOUNT_ID`
  - `SNAP_CLIENT_ID`
  - `SNAP_CLIENT_SECRET`
  - `SNAP_REFRESH_TOKEN`
  - `SNAP_AD_ACCOUNT_ID`
  - `TIKTOK_APP_ID`
  - `TIKTOK_APP_SECRET`
  - `TIKTOK_ACCESS_TOKEN`
  - `TIKTOK_REFRESH_TOKEN`
  - `TIKTOK_ADVERTISER_ID`
- Token persistence uses encrypted casts in `App\Infrastructure\Ads\Persistence\Models\AdsPlatformAccount`.

### Required policy
- Never hardcode platform credentials in controllers, services, or migrations.
- Never return full access tokens in API responses.
- Never log raw tokens.

## OAuth / Token Strategy

### Verified in code
- `App\Infrastructure\Ads\Persistence\EloquentTokenStore` can refresh Snap and TikTok tokens.
- Meta uses long-lived/system-user token behavior.
- Current route surface only supports manual token upsert/update.

### Recommended
1. Add `connect` and `callback` endpoints per platform.
2. Store granted scopes per account.
3. Persist token expiry and refresh result history.
4. Add re-consent failure handling when scopes drift.

## Webhook Strategy

### Current gap
No Ads webhook routes are verified.

### Recommended endpoints
- `POST /api/ads/webhooks/meta/leads`
- `POST /api/ads/webhooks/tiktok/leads`
- `POST /api/ads/webhooks/snap/leads`

### Required controls
- Signature validation per platform.
- Replay protection using timestamp/nonce windows.
- Idempotent receipt storage by provider event ID.
- Queue-first processing.
- Dead-letter handling for repeated failures.

## Campaign / Ad Account Mapping

### Verified in code
- Platform account rows are stored in `ads_platform_accounts`.
- Structure rows are stored in `ads_campaigns`, `ads_ad_sets`, `ads_ads`.
- Filters by `platform`, `account_id`, `campaign_id`, `adset_id`, `ad_id`, and `form_id` exist in Ads controllers.

### Recommended additions
- Map internal ERP project or developer identifiers to campaign/ad set/ad IDs.
- Add page/form/pixel mapping tables if lead attribution becomes multi-page or multi-brand.

## Lead Sync

### Verified in code
- Read-time lead fetch exists in `App\Http\Controllers\Ads\AdsLeadsController::index`.
- Optional CRM sync exists through `App\Services\Ads\PlatformLeadSyncService`.
- Stored lead submissions are persisted in `ads_lead_submissions`.
- Deduplication exists on `platform + account_id + lead_id`.

### Recommended
- Treat webhook lead ingestion as primary where provider supports it.
- Keep pull sync as backfill and reconciliation.
- Add lead-source reconciliation reports between `ads_lead_submissions` and CRM `leads`.

## Lead Export CSV

### Verified in code
- Immediate lead export exists at `GET /api/ads/leads/export`.
- Snap CSV normalization exists at `POST /api/ads/leads/export-snap`.
- Queued export creation exists at `POST /api/ads/exports/leads`.

## Campaign Reporting

### Verified in code
- Platform, campaign, and daily trend reporting exist in `App\Http\Controllers\Ads\AdsReportingController`.
- Structure and raw insights browsing exist in `AdsInsightsController`.

### Recommended
- Add ROI attribution once ERP revenue linkage is normalized.
- Distinguish ad-platform metrics from downstream CRM funnel metrics in API labels.

## Outcome Publishing

### Verified in code
- `POST /api/ads/outcomes` writes conversion/outcome events through `App\Application\Ads\ComputeCustomerOutcome`.
- `App\Infrastructure\Ads\Persistence\EloquentOutcomeStore` supports:
  - idempotent enqueue
  - retry_count
  - next_attempt_at
  - dead-lettering

### Recommended
- Expose delivery failure categories in API responses for operations dashboards.
- Add provider-specific validation before enqueue when critical identifiers are missing.

## Rate Limits / Retry Strategy

### Verified in code
- `App\Infrastructure\Ads\BaseAdsClient` retries 429 and 5xx with backoff and jitter.
- `App\Infrastructure\Ads\Persistence\EloquentOutcomeStore::markFailed()` applies exponential retry scheduling.

### Recommended
- Add provider-specific max retry thresholds by operation type.
- Separate sync retry policy from outcome publish retry policy.
- Record provider request IDs when available.

## Security / Privacy Requirements
1. Keep secrets only in `.env` and encrypted DB fields.
2. Hash customer identifiers before sending conversion APIs where required.
3. Minimize stored PII in `ads_lead_submissions.raw_payload`.
4. Enforce role and permission checks on all Ads endpoints.
5. Queue external provider operations; do not block user requests on long provider calls.
6. Add webhook signature validation before enabling inbound lead webhooks.

## Platform Expansion Roadmap

### Google / YouTube
- Recommended next because the current architecture already has:
  - account abstraction
  - campaign/insight storage
  - outbox outcome model
- Required additions:
  - Google Ads OAuth
  - customer account hierarchy support
  - offline conversion upload API
  - YouTube campaign reporting mappings

### LinkedIn
- Add only after generalizing account and campaign metadata fields that are currently tuned to Meta/Snap/TikTok assumptions.

### SerpAPI / Market Research
- Add as a separate market-intelligence module, not as part of the Ads CRUD/reporting API group.
