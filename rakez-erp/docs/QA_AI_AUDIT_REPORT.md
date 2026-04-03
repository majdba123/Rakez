# تقرير QA / أمان — نظام المساعد والذكاء الاصطناعي (مستند إلى الكود والتشغيل الفعلي)

**تاريخ التشغيل المرجعي:** 2026-03-30  
**المستودع:** `rakez-erp`  
**ملاحظة منهجية:** لا يُفترض أي سلوك غير ظاهر في الكود؛ ما لم يُنفَّذ في الكود أو يُثبت بتشغيل يُذكر كفجوة.

---

## 1. خريطة النظام المرتبط بالمساعد والذكاء الاصطناعي

| المسار (تحت `auth:sanctum` ما لم يُذكر خلافه) | الغرض | ملاحظات من الكود |
|-----------------------------------------------|--------|-------------------|
| `POST /api/ai/ask` | سؤال لمرة واحدة (v1) | `AskQuestionRequest`: `question` مطلوب، `max:2000`، `section` من `config('ai_sections')`، `throttle:ai-assistant` |
| `POST /api/ai/chat` | محادثة v1 (مع دعم stream عبر الحقل `stream`) | `ChatRequest`: `message` مطلوب، `max:2000`، `session_id` اختياري UUID. عند تفعيل المسار الهجين (`shouldUseOrchestrator`) يُمرَّر نفس `policy_snapshot` إلى `RakizAiOrchestrator` كما في `tools/chat`. |
| `GET /api/ai/conversations`، `DELETE /api/ai/conversations/{sessionId}` | جلسات المحادثة | نفس مجموعة الـ throttle |
| `GET /api/ai/sections` | الأقسام المتاحة حسب القدرات | |
| `POST /api/ai/tools/chat`، `POST /api/ai/tools/stream` | أوركسترا الأدوات (v2) | `AiV2Controller`: تحقق مباشر `message` مطلوب `max:16000` — **اختلاف عن v1**. سياسة ما قبل الـ LLM (`policy_snapshot`، `tool_mode`، رفض KPI/حساس) في `App\Services\AI\Policy\RakizAiPolicyContextBuilder` وتُستدعى من المتحكم ومن `AIAssistantService::chatWithOrchestrator` عند المسار الهجين. **Stream:** `AiAssistantException` → SSE يحتوي `error_code` + `message`؛ أي `Throwable` آخر → رسالة عامة فقط بدون تسريب داخلي. |
| `POST /api/ai/v2/chat`، `POST /api/ai/v2/stream` | أسماء بديلة لنفس منطق v2 | |
| `POST/GET/DELETE ... /api/ai/documents/*`، `POST /api/ai/documents/search` | RAG / المستندات | سياسات وصلاحيات في المتحكمات والخدمات (لا يُفترض السلوك هنا دون قراءة كل مسار) |
| `POST /api/ai/assistant/chat` | مسار مساعد منفصل (`AssistantChatController`) | `auth:sanctum` فقط — **ليس** تحت `prefix('ai')->throttle:ai-assistant` في `routes/api.php` |
| `GET|POST|PUT|DELETE /api/ai/knowledge` | إدارة قاعدة المعرفة | `auth:sanctum` + `role:admin` |
| `Route::prefix('ai/calls')` | مكالمات AI | `auth:sanctum` + `role:admin|sales|sales_leader|marketing` + صلاحيات `ai-calls.manage` على العمليات |
| `POST /api/webhooks/twilio/...` | Webhooks Twilio | **بدون** مستخدم؛ `ValidateTwilioSignature` فقط |

**حد المعدل:** `Route::prefix('ai')->middleware('throttle:ai-assistant')` لمجموعة ask/chat/tools/v2/documents (وليس لـ `assistant/chat` ولا `ai/calls` ولا `ai/knowledge` حسب التعريف الحالي في `routes/api.php`). القيمة من `config('ai_assistant.rate_limits.per_minute')` و`AppServiceProvider::configureRateLimiting`.

---

## 2. أنواع المستخدمين والأدوار والصلاحيات

- **مصدر الأدوار والصلاحيات في التشغيل:** Spatie (`roles` / `permissions`، guard `web` في الـ seeder)، مع **تعريفات** و**خريطة أدوار أولية** في `config/ai_capabilities.php` (`definitions`، `bootstrap_role_map`). التعليق في الملف يوضح أن `bootstrap_role_map` للـ seeding وليس بالضرورة وقت التشغيل الوحيد.
- **أدوار مذكورة في `bootstrap_role_map` (مفاتيح المصفوفة):** `admin`, `project_management`, `editor`, `developer`, `marketing`, `sales`, `sales_leader`, `hr`, `credit`, `accounting`, `inventory`, `default`, `accountant` — بالإضافة إلى أدوار أخرى قد تُنشأ في seeders إضافية (مثل `accountant` في `CommissionRolesSeeder`).
- **صلاحية المساعد الأساسية:** `use-ai-assistant` — يفرضها `AiV2Controller` و`ToolRegistry` لمسارات الأدوات.
- **بوابة أداة إضافية:** `config('ai_assistant.v2.tool_gates')` يربط `tool_kpi_sales` بـ `sales.dashboard.view`.
- **Gate للإدارة:** تعليق في `ai_capabilities.php`: `Gate::before` يمنح `admin` تجاوزًا كاملاً لـ `can()` — يجب اعتبار ذلك في مراجعات الأمان.
- **معلومات المعرفة:** صلاحية `manage-ai-knowledge` مُعرَّفة في التعريفات؛ مسار REST `/api/ai/knowledge` مضبوط على **دور admin** فقط في المسار.

---

## 3. الأدوات المتاحة للمساعد

المصدر: `App\Services\AI\ToolRegistry` — **17** أداة مسجّلة:

`tool_search_records`, `tool_get_lead_summary`, `tool_get_project_summary`, `tool_get_contract_status`, `tool_kpi_sales`, `tool_explain_access`, `tool_rag_search`, `tool_campaign_advisor`, `tool_hiring_advisor`, `tool_finance_calculator`, `tool_marketing_analytics`, `tool_sales_advisor`, `tool_smart_distribution`, `tool_employee_recommendation`, `tool_campaign_funnel`, `tool_roas_optimizer`, `tool_ai_call_status`.

الفلترة: بدون `use-ai-assistant` → لا أدوات؛ مع بوابات `tool_gates` إن وُجدت.

---

## 4. مصفوفة السيناريوهات (مرجعية)

| فئة | أمثلة (تم تغطية جزء كبير في Feature/Integration) |
|-----|---------------------------------------------------|
| مصادقة | 401 بدون توكن؛ 403 بدون `use-ai-assistant` على v2 |
| تحقق | 422 حقول مطلوبة ask/chat/tools؛ سياق الأقسام من `ai_sections` |
| أدوات | تنفيذ مسموح/ممنوع؛ بوابة `tool_kpi_sales`؛ أدوات غير معروفة |
| معدل / تعطيل | اختبار throttle معطّل كـ flaky في `AIAssistantIntegrationTest` — **لا يُعتمد كدليل إنتاج** |
| RAG / مستندات | رفع، بحث، سياسات الوصول |
| مكالمات | `ai/calls` مع أدوار + `ai-calls.manage` |
| Twilio | توقيع، حالات المكالمة |
| E2E حقيقي | مجموعة `@group ai-e2e-real` تتطلب مفاتيح وشبكة |

---

## 5. التستات التي أُضيفت أو أُصلحت في هذه الجولة

| ملف | الغرض |
|-----|--------|
| `tests/Unit/AI/ToolRegistryGatesTest.php` | عدد الأدوات 17؛ منع التنفيذ بدون `use-ai-assistant`؛ بوابة `tool_kpi_sales` ↔ `sales.dashboard.view` |
| `tests/Feature/AI/AiApiUnauthenticatedTest.php` | 401 لمجموعة من مسارات `/api/ai/*` بما فيها `assistant/chat` |
| `tests/Feature/AI/AiApiValidationTest.php` | 422 لـ ask/chat/tools عند غياب الحقول (بدون OpenAI fake) |
| `tests/Feature/AI/AssistantKnowledgeTest.php` | تصحيح المسارات إلى `/api/ai/knowledge`؛ مطابقة `meta.pagination` للاستجابة الفعلية |
| `tests/Unit/AI/ContextBuilderTest.php` | استخدام `app(ContextBuilder::class)` بدل تهيئة يدوية كسرت بعد تغيّر حقن `MarketingProjectService` |
| `app/Http/Controllers/AI/AssistantKnowledgeController.php` | `filled('is_active')` بدل `has('is_active')`؛ ترقيم الصفحات عبر `query('page')` + `skip/take` لتفادي دمج `page` غير مقصود من الطلب؛ استجابة ترقيم يدوية متسقة |

---

## 6. نتائج التشغيل

| الأمر | النتيجة |
|-------|---------|
| `php artisan test tests/Feature/AI/AssistantKnowledgeTest.php` | نجاح (20 اختبارًا) |
| `php artisan test tests/Unit/AI/ToolRegistryGatesTest.php tests/Feature/AI/AiApiUnauthenticatedTest.php tests/Feature/AI/AiApiValidationTest.php` | نجاح |
| `php artisan test tests/Unit/AI/ContextBuilderTest.php` | نجاح |
| `php artisan test --group=ai-e2e-real` | **63 ناجح، 1 متخطى**، المدة ~313s |
| `composer run test:e2e-ai` | **فشل بسبب timeout 300s لعملية Composer** — التشغيل المباشر لـ `php artisan test --group=ai-e2e-real` أكمل بنجاح |

**متخطى:** `Tests\Integration\AI\AIApiRealEndpointsToolAndMemoryTest` — السبب المذكور في المخرجات: النموذج لم يصدر استدعاء أداة بالشكل المتوقع (سلوك غير حتمي للنموذج الخارجي).

---

## 7. الثغرات والفجوات

1. **اختلاف حدود الرسالة:** v1 (`AskQuestionRequest` / `ChatRequest`) `max:2000` بينما `AiV2Controller` يفرض `max:16000` لمسار الأدوات — سلوك غير موحّد وقد يربك العميل أو المراجعات الأمنية.
2. **تجاوز admin لـ `can()`** — يقلل فائدة اختبارات الصلاحيات لحسابات admin في سيناريوهات واقعية.
3. **اختبار throttle معطّل** (`AIAssistantIntegrationTest`) — لا تغطية موثوقة لحد المعدل في CI.
4. **E2E يعتمد على نموذج خارجي** — اختبار الأدوات الصارم قد يُتخطى أو يفشل بشكل غير حتمي.
5. **`composer test:e2e-ai`** قد يقطع قبل انتهاء المجموعة إذا بقي حد 300s — يحتاج زيادة timeout أو تشغيل `php artisan test` مباشرة.
6. **مساعد HTTP منفصل:** `/api/ai/assistant/chat` خارج مجموعة `throttle:ai-assistant` — سطح هجوم/تكلفة مختلف عن بقية `/api/ai/*`.

---

## 8. أخطر المشاكل (ترتيب عملي)

1. **Twilio webhooks بدون مصادقة مستخدم** — الاعتماد الكامل على التحقق من التوقيع؛ أي خطأ في الإعداد أو مفتاح يعرض مسارات الـ voice/gather/status.
2. **Gate::before للإدارة** — أي ثغرة في تعيين دور admin تتضخم إلى تجاوز كامل للصلاحيات.
3. **سلوك غير حتمي للنموذج في E2E** — لا يضمن أن استدعاء الأدوات سيعمل دائمًا تحت الضغط أو تغيّر النموذج.

---

## 9. توصيات التحسين

1. توحيد حد `message`/`question` بين v1 وv2 أو توثيق الفروق صراحة في واجهة API واختبارات تلتقط الانحراف.
2. رفع `process-timeout` في `composer.json` لسكربت `test:e2e-ai` أو توثيق تشغيل `php artisan test --group=ai-e2e-real` للـ CI.
3. استقرار اختبار الأدوات E2E: إما mock للطبقة التي تُقرر استدعاء الأداة أو تكرار محاولات مع حد أعلى ومعايير قبول واضحة.
4. مراجعة ما إذا كان يجب تطبيق `throttle:ai-assistant` على `POST /api/ai/assistant/chat` لمواءمة تكلفة الاستخدام.
5. إبقاء `AssistantKnowledgeController` على `query('page')` فقط لمعامل الترقيم وتجنب `input('page')` المدمج من جلسات اختبار أخرى.

---

*نهاية التقرير.*
