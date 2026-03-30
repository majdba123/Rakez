# Stream Parity Before/After Evidence

## Before (Fail)

تشغيل:
`php artisan test tests/Integration/AI/AiRealQaStreamParityTest.php`

النتيجة:
- FAIL `stream non stream behavioral parity for allowed case`
- FAIL `stream non stream behavioral parity for refusal case`
- السبب الأساسي: `streamText` كان فارغًا أولًا، ثم ظهر عدم تكافؤ `Denied/scope parity`.

## Changes Applied

- توحيد policy pre-check في `AiV2Controller` قبل استدعاء LLM للمسارين `chat` و`stream`.
- إضافة deterministic policy snapshot موحد وحقنه في `page_context` للمسارين.
- إضافة early policy gate موحد (خصوصًا KPI sales بدون صلاحية + sensitive probes).
- إضافة snapshot normalization موحد لمخرجات الحالات conceptual/no-data.
- تعديل `RakizAiOrchestrator` لاستخدام `policy_snapshot.tool_mode=none` لتعطيل الأدوات بشكل حتمي عندما يلزم.
- تحسين parser في `AiRealQaStreamParityTest` لاستخراج SSE بمرونة أعلى.

## After (Pass)

إعادة تشغيل:
`php artisan test tests/Integration/AI/AiRealQaStreamParityTest.php`

النتيجة:
- PASS `stream non stream behavioral parity for allowed case`
- PASS `stream non stream behavioral parity for refusal case`

ملخص:
- قبل: 2 FAILED
- بعد: 2 PASSED

