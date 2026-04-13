## Phase 6 Realtime Voice Decision Gate

This is a decision gate, not an implementation authorization by itself.

## Realtime remains blocked until all conditions below are met

- Phase 0 through Phase 5 are complete and green
- text assistant behavior is stable in sync and stream modes
- tool orchestration policy is consistent
- voice fallback MVP is accepted in production-like QA
- audit coverage exists for every voice turn state

## Questions that must be answered with evidence

### Product need

- Is push-to-talk fallback materially insufficient?
- Are users blocked by turn latency, or only mildly inconvenienced?
- Is interruption/barge-in required for task completion, or only desirable?

### Technical readiness

- Can the current session model support partial turns safely?
- Can audit trails represent partial transcript, interruption, and resumed turns?
- Can tool safety survive concurrent or overlapping voice turns?
- Can the frontend sustain a dedicated realtime transport lifecycle?

### Operational readiness

- Is cost acceptable for always-on or near-live sessions?
- Can failures degrade cleanly back to text or push-to-talk fallback?
- Do observability and support teams have enough telemetry to debug live audio issues?

## Mandatory no-go criteria

- no partial-turn audit model
- no concurrency policy for tool calls
- no frontend UX for interruption state
- no reliable fallback path
- no measured evidence that realtime materially improves the core workflows

## Go criteria

- explicit latency target and measured baseline gap
- dedicated session/transport design
- explicit interruption semantics
- explicit concurrency/tool policy
- explicit rollback-to-fallback plan

## Explicitly forbidden before gate passes

- enabling OpenAI Realtime API in production paths
- adding WebRTC transport to the current fallback endpoint
- simulating full duplex on top of `/api/ai/voice/chat`
