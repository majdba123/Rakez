# Final Cleanup Report (Strict) — جولة الأدلة والتوحيد

**تاريخ المرجع:** 2026-03-30  
**النطاق:** توحيد سياسة الأوركسترا بين `tools/*` والمسار الهجين، عقود أخطاء stream، اختبارات الوحدة/hard-proof، توثيق صادق.

---

## 1) Full Final Audit (موسّع، مبني على الكود)

| Issue ID | Type | Location | Risk | Why it matters | Safe fix strategy |
|---|---|---|---|---|---|
| FA-01 | Duplicate policy logic | `AiV2Controller` (قبل الاستخراج) | عالٍ | قرار مختلف حسب نقطة الدخول | `RakizAiPolicyContextBuilder` مشترك |
| FA-02 | Hybrid بدون `policy_snapshot` | `AIAssistantService::chatWithOrchestrator` | **حرج** | `tool_mode=none` وبوابات ما قبل LLM لا تنطبق على `/api/ai/chat` | تمرير `policy_snapshot` + early gate مثل v2 |
| FA-03 | تسرّب رسالة خطأ stream | `AiV2Controller::stream` `catch (\Throwable)` | متوسط–عالٍ | `getMessage()` قد يكشف داخليات | `AiAssistantException` → `error_code`؛ غير ذلك رسالة عامة |
| FA-04 | FA-05 tests | `AiRealQaToolFailureResilienceTest` | متوسط | confidence دون ربط audit | عند استدعاء الأداة: التحقق من صف `tool_call` و`denied` لوضع `unauthorized` |
| FA-05 | G-09 multi-tool | Hard-proof | متوسط | ادعاء سلسلة أدوات غير مثبت | اختبار يطلب ≥2 أداة في audit (يعتمد على الموديل) |
| FA-06 | Suite منفصلة لـ hard-proof | `phpunit.xml` | منخفض | تشغيل جزئي | `testsuite name="AI-HardProof"` |

---

## 2) Target Architecture (ملخّص نهائي)

- **Policy / pre-LLM gates:** `App\Services\AI\Policy\RakizAiPolicyContextBuilder` — يبنى `policy_snapshot`، `earlyPolicyGateResponse`، `applySnapshotNormalization`.
- **استدعاء من:** `AiV2Controller` (حقن تبعية)، `AIAssistantService::chatWithOrchestrator` (حقن تبعية).
- **Tool orchestration:** `RakizAiOrchestrator` + `ToolRegistry`؛ `tool_mode` من الـ snapshot.
- **Post-decision confidence:** `RakizAiOrchestrator::applyPostDecisionNormalization` (بدون تغيير في هذه الجولة).
- **Stream vs JSON:** نفس المسار المنطقي؛ أخطاء SSE موحّدة مع `chat` حيث ينطبق `AiAssistantException`.

---

## 3) ما تم تنفيذه (Change log)

| Change ID | Category | Files affected | Why | Risk | Result |
|---|---|---|---|---|---|
| CH-09 | Refactor | `app/Services/AI/Policy/RakizAiPolicyContextBuilder.php` (جديد) | مركزية سياسة v2 قابلة للاختبار | متوسط | مصدر واحد للـ snapshot والبوابات |
| CH-10 | Refactor | `app/Http/Controllers/AI/AiV2Controller.php` | استخدام الباني المشترك + stream آمن | متوسط | متحكم أرفع؛ أخطاء stream متوافقة |
| CH-11 | Fix / parity | `app/Services/AI/AIAssistantService.php` | هجين يمرّر `policy_snapshot` و early gate | **حرج→مغلق** | سلوك متوافق مع `tools/chat` |
| CH-12 | Tests | `tests/Unit/AI/Policy/RakizAiPolicyContextBuilderTest.php` | إثبات `tool_mode` والبوابات | منخفض | 6 اختبارات وحدة |
| CH-13 | Tests | `tests/Integration/AI/AiRealQaHardProofToolsDecisionTest.php` | G-09 جزئي | متوسط | اختبار multi-tool تسلسلي |
| CH-14 | Tests | `tests/Integration/AI/AiRealQaToolFailureResilienceTest.php` | FA-05 audit coupling | منخفض | `lastToolCallInputAfter` + `denied` |
| CH-15 | Tests | `tests/Feature/AI/AiScenarioMatrixFeatureTest.php` | نص الرفض في stream | منخفض | يطابق رسالة JSON 403 |
| CH-16 | Config | `phpunit.xml` | suite `AI-HardProof` | منخفض | `php artisan test --testsuite=AI-HardProof` |
| CH-17 | Docs | `docs/QA_AI_AUDIT_REPORT.md`, `tests/AI_QA_PHASE2_5_GAP_AUDIT_AR.md` | صدق التوثيق | منخفض | G-09/G-11 + مسارات محدّثة |

**ما رُفض تغييره (في هذه الجولة):**

- توحيد `max:2000` (v1) مع `max:16000` (v2) — قد يكسر عقود الواجهة؛ بقي اختلاف **مقصود ومذكور** في التوثيق.
- حذف واسع لملفات/اختبارات خارج نطاق AI دون تحقق من الاعتماديات.

---

## 4) Final Architecture Summary (أين ماذا)

| المحور | الموقع |
|--------|--------|
| صلاحيات ما قبل الـ LLM (KPI، حساس، `tool_mode`) | `RakizAiPolicyContextBuilder` |
| قرار الأدوات والتنفيذ | `RakizAiOrchestrator` + `ToolRegistry` |
| Stream / chat المشترك (v2) | نفس الـ snapshot والأوركسترا؛ SSE يعكس أخطاء آمنة |
| الثقة بعد فشل أداة | `RakizAiOrchestrator::applyPostDecisionNormalization` |
| استرجاع / RAG | دون تغيير في هذه الجولة (كما في الكود الحالي) |

---

## 5) Final QA Status

**تم تشغيل محليًا (بدون مفتاح OpenAI حقيقي):**

- `tests/Unit/AI/Policy/RakizAiPolicyContextBuilderTest.php`
- `tests/Feature/AI/AiScenarioMatrixFeatureTest.php`
- `tests/Feature/AI/AIAssistantServiceTest.php`
- `tests/Feature/AI/AiToolsPermissionTest.php`
- `tests/Feature/AI/AiApiUnauthenticatedTest.php`

**اختبارات `--testsuite=Unit` الكاملة:** في هذا البيئة ظهرت فشلات مسبقة غير مرتبطة بالتغييرات (مثل `CommissionDistributionTest` / CHECK `type` على SQLite) — **لا تُنسب لهذه الجولة**.

**Hard-proof الحي (`composer run test:ai-hard-proof-live`):** يتطلب `AI_REAL_TESTS` + مفتاحًا حقيقيًا؛ يُشغَّل في بيئة الاعتماد.

### Scores (/10) — تقدير بعد التوحيد والاختبارات المضافة

| المحور | الدرجة | ملاحظة |
|--------|--------|--------|
| technical reliability | 8.8 | توحيد policy يقلل مسارات خاطئة |
| behavioral intelligence | 7.8 | ما زال يعتمد على الموديل في multi-tool |
| security robustness | 9.0 | تقليل تسرّب SSE + تكافؤ هجين |
| tool-orchestration reliability | 8.5 | snapshot موحّد |
| retrieval quality | 7.6 | دون تغيير في هذه الجولة |
| maintainability | 8.6 | طبقة Policy واضحة |
| codebase cleanliness | 8.2 | أقل تكرارًا في المتحكم |
| production trustworthiness | 8.4 | توثيق واختبارات أوضح |

---

## 6) Documentation Truth Alignment

- **proven (كود + وحدات):** بناء `policy_snapshot`، `tool_mode=none` للأسئلة المفهومية، بوابات KPI/حساس، تطبيع `access_notes`، stream `AiAssistantException` + رسالة عامة لغير المتوقع.
- **partially proven:** G-09 multi-tool (يعتمد على امتثال LLM)؛ G-04/G-10 كما في مصفوفة الفجوات.
- **not yet proven:** combinatorial لكل الأدوات؛ معايير زمن/تكلفة توكن كـ gates؛ suite Unit كاملة خضراء على SQLite لهذا المشروع.

---

## 7) ما الذي لم أصل به للنهاية بعد

- **اختبارات hard-proof الحية** لم تُشغَّل هنا بمفتاح حقيقي؛ يجب تأكيد `test:ai-hard-proof-live` في CI/Staging.
- **اختبار `test_sequential_multi_tool_trace_*`** قد يكون flaky إذا تجاهل الموديل خطوة ثانية؛ يُراجع عند تغيير الموديل أو البرومبت.
- **فشل Unit suite الأوسع** (عمولات / CHECK SQLite) يحتاج جولة منفصلة خارج نطاق AI.
- **`streamChat` الهجين** (v1) إن وُجد مسار أوركسترا عبر التدفق لاحقًا — غير مطبّق في هذه الجولة (المسار الحالي يمر عبر `chat` فقط للأوركسترا).

---

## 8) Honesty section (إلزامي)

| البند | الوضع |
|--------|--------|
| Gaps متبقية | شمولية كل الأدوات، rubric نصي بالكامل، flaky محتمل لـ multi-tool |
| غير مثبت بلا مفتاح حقيقي | مجموعة AI-HardProof كاملة على الإنتاج الفعلي |
| ديون تقنية صغيرة | `earlyPolicyGateResponse` يستقبل `section` غير مستخدم حاليًا في الجسم (لتوافق API) |
| Trade-offs | الإبقاء على حدّي رسالة v1/v2 مختلفين لتجنب كسر الواجهات |
