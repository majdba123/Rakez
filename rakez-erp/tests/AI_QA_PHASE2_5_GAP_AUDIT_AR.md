# Gap Audit - AI Real QA (Phase 2.5 Hard Proof)

| Gap ID | Type | Claimed | Actually Proven | Risk | Needed Proof |
|---|---|---|---|---|---|
| G-01 | Roles Coverage | كل roles مغطاة | في Phase2 السابقة: coverage جزئي توليدي. بعد `AiRealQaRoleDepthMatrixTest`: تم اختبار 13 role على allowed/forbidden/boundary/malicious/usefulness | متوسط | evidence role-by-role + endpoint traces (تمت إضافته) |
| G-02 | Endpoints Coverage | endpoints المهمة included | بعض endpoints كانت "included" بالوصف فقط. الآن مثبتة فعليًا على: `tools/chat`, `tools/stream`, `ask`, `chat`, `knowledge`, `documents`, `documents/search`, `calls`, `sections`. | متوسط | تشغيل test suites مخصصة لكل endpoint + توثيق PASS/FAIL |
| G-03 | Tool-specific claims | حالات أداة محددة = جودة عالية | في Phase2 S01 كان Tool Calls = `[]` رغم claim مرتبط بـ `tool_kpi_sales` | عالي | decision trace من `ai_audit_trail` لكل case |
| G-04 | Tool-decision correctness | قرار استخدام الأداة صحيح | مثبت جزئيًا: 3 حالات hard-proof (must-call / must-not-call / forbidden tool). غير مثبت شاملًا لكل الأدوات | عالي | Decision table + per-tool deterministic cases |
| G-05 | Stream parity | parity جيدة | **تم إغلاقها**: `AiRealQaStreamParityTest` أصبحت PASS بعد توحيد policy pre-check + snapshot | كان عالي، أصبح منخفض | استمرار مراقبة حالات parity الموسعة |
| G-06 | Tool failure resilience | fallback resilient | **تم إغلاقها**: `AiRealQaToolFailureResilienceTest` أصبحت PASS مع خفض confidence بعد فشل الأداة | كان عالي جدًا، أصبح منخفض | إبقاء guard ضد false-certainty |
| G-07 | Retrieval quality | retrieval موثوقة | **تحسنت**: `AiRealQaRetrievalHardCasesTest` PASS (relevant/zero/scope/mixed) | متوسط | توسيع benchmark relevance على dataset أكبر |
| G-08 | Heuristic heaviness | rubric صارمة بالكامل | ما زال جزء من التقييم يعتمد نصيًا (keywords) | متوسط | hard-proof pass يتطلب trace evidence + decision evidence (تمت إضافته جزئيًا) |
| G-09 | Multi-step / multi-tool | multi-tool مثبتة | غير مثبتة بشكل قاطع حتى الآن | متوسط | case إلزامي بأكثر من tool مع trace count + tool order |
| G-10 | Assistant quality claim | "ذكاء عالي الجودة" مثبت | **مثبت جزئيًا بقوة أعلى** بعد إغلاق إخفاقات stream/failure/retrieval في suite الحالية | متوسط | تعميم الإثبات على كل الأدوات المسجلة |

