# مصفوفة سيناريوهات الاختبار — المساعد الذكي والأدوات

**الأعمدة المنطقية:** Role × User.type × Permission × AI Capability (من Spatie/الديناميكي) × Section × Tool × Input Case × Expected Behavior

**ملاحظة:** `Role` هنا اسم دور Spatie من `bootstrap_role_map`. `User.type` يطابق الدور عادةً (`sales` + `is_manager` → `sales_leader`).

---

## 1) سيناريوهات النجاح الطبيعي

| ID | Role | type | صلاحية أساسية | قسم / أداة | إدخال | سلوك متوقع | تغطية حالية |
|----|------|------|----------------|------------|--------|-------------|--------------|
| N-01 | أي دور بـ `use-ai-assistant` | — | ✓ | بدون section | سؤال نصي | 200، رد نصي | `AIAssistantControllerTest`, `AIAssistantServiceTest` |
| N-01b | default | default | ✓ | — | أقسام متاحة بدون تسويق | `marketing_dashboard` غير مضمن | `AiScenarioMatrixFeatureTest::test_default_role_sections_exclude_marketing_dashboard` |
| N-02 | admin | admin | ✓ | `general` | سؤال | أقسام متاحة واسعة | `AssistantChatTest` (مع seed) |
| N-03 | sales | sales | ✓ + `sales.dashboard.view` | orchestrator يستدعي أداة | رسالة تحتوي kpi/campaign | مسار أدوات محتمل | [GAP] تكامل orchestrator مع mock كامل |
| N-04 | sales | sales | ✓ | `tool_kpi_sales` | استدعاء registry | نجاح إن وُجدت بيانات | `AllToolsTest` (أدوات أخرى)، `ToolRegistryGatesTest` للبوابة |
| N-05 | متعدد | — | ✓ | عدة أدوات متتالية | — | حلقة أدوات في `RakizAiOrchestrator` | [GAP] وحدة للـ orchestrator بدون OpenAI حقيقي |
| N-06 | أي | — | ✓ | — | محادثة طويلة | إنشاء `is_summary` بعد عتبة | `AIAssistantServiceTest`, `AIAssistantIntegrationTest` |
| N-07 | sales | sales | ✓ + `sales.dashboard.view` | `sales` | سؤال ضمن القسم | 200 | [GAP] feature صريح لـ section=sales مع fake |
| N-08 | marketing | marketing | ✓ + `marketing.dashboard.view` | `marketing_dashboard` | — | القسم يظهر في `/api/ai/sections` | `AiScenarioMatrixFeatureTest::test_marketing_role_sections_include_marketing_dashboard` |

---

## 2) سيناريوهات المنع

| ID | Role | صلاحية | قسم/أداة | إدخال | متوقع | تغطية |
|----|------|--------|----------|--------|--------|--------|
| D-01 | أي بدون دور/صلاحية | ✗ `use-ai-assistant` | — | POST `/api/ai/tools/chat` | 403 | `AiToolsPermissionTest` |
| D-02 | default | ✓ AI، ✗ `sales.dashboard.view` | `tool_kpi_sales` | execute | `allowed: false` أو غير موجود في allowlist | `ToolRegistryGatesTest` |
| D-03 | default | ✓ AI | `sales` section | ask/chat | 403 `UNAUTHORIZED_SECTION` | `AiScenarioMatrixFeatureTest::test_ask_with_section_sales_returns_403_for_default_bootstrap_user` |
| D-04 | marketing | ✓ AI | `roas_optimizer` | ask | 403 (لا يملك `accounting.dashboard.view`) | `AiScenarioMatrixFeatureTest::test_ask_with_section_roas_optimizer_returns_403_for_marketing_user_missing_accounting_capability` |
| D-05 | user | ✓ جزئي | `contracts` + contract غيره | context | استبعاد/403 | `AuthorizationTest`, `ContextBuilderTest` |
| D-06 | — | ✗ | — | POST `/api/ai/assistant/chat` | 403 | `AssistantChatTest` |
| D-07 | accounting | ✓ AI لكن | `/api/ai/calls` | — | 403 دور middleware | [GAP] feature `AiCallControllerTest` يغطي جزئياً |

---

## 3) إدخال حدّي

| ID | مسار | إدخال | متوقع | تغطية |
|----|------|--------|--------|--------|
| E-01 | ask | `question` > 2000 حرف | 422 | `AIAssistantControllerTest::test_ask_endpoint_validates_question_max_length` |
| E-02 | ask/chat | حقل مفقود | 422 | `AiApiValidationTest`, `AIAssistantControllerTest` |
| E-03 | ask | `section` غير معروف | 422 | `AIAssistantControllerTest` |
| E-04 | ask | `context` يخالف schema | 422 | `AIAssistantControllerTest`, `ContextValidationTest` |
| E-05 | ask | سؤال = مسافات فقط | 422 (يُعتبر فارغاً بعد التحقق) | `AiScenarioMatrixFeatureTest::test_whitespace_only_question_fails_validation_as_empty` |
| E-06 | body | JSON مشوّه | 400 أو 422 | `AiScenarioMatrixFeatureTest::test_malformed_json_body_returns_client_error` |
| E-07 | tools | `message` > 16000 | 422 | `AiScenarioMatrixFeatureTest::test_tools_chat_rejects_message_longer_than_16000_chars` |
| E-08 | — | حقن أوامر في الـ prompt | يُنقّح/لا يثق | [GAP] اختبار سلوكي؛ PII: `PiiRedactionMiddlewareTest` |

---

## 4) سيناريوهات الأدوات (ToolRegistry)

| ID | حالة | متوقع | تغطية |
|----|------|--------|--------|
| T-01 | اسم أداة غير مسجل | خطأ Unknown tool | `ToolRegistryEdgeCasesTest::test_unknown_tool_returns_error_shape` |
| T-02 | `tool_search_records` بدون query | رسالة خطأ من الأداة | `ToolRegistryEdgeCasesTest::test_search_records_with_empty_query_returns_tool_error` |
| T-03 | استدعاء صحيح | `source_refs` + result | `AllToolsTest` |
| T-04 | صلاحية ممنوعة | `Permission denied` | `AllToolsTest`, `ToolRegistryGatesTest` |
| T-05 | أداة ترمي استثناء | معالجة في الأداة | [GAP] يحتاج mock أداة ترمي |
| T-06 | timeout | — | [GAP] طبقة البنية التحتية |

---

## 5) سيناريوهات الرد النهائي

| ID | الموضوع | تغطية |
|----|---------|--------|
| R-01 | رد فارغ من OpenAI | `OpenAIResponsesClientTest` |
| R-02 | رد بعد فشل أداة | [GAP] orchestrator |
| R-03 | تدقيق/تلخيص مدخلات | `AiAuditTrailTest` |

---

## 6) ذاكرة وسياق

| ID | السيناريو | تغطية |
|----|-----------|--------|
| M-01 | ملخص تلقائي بعد N رسائل | `AIAssistantServiceTest` |
| M-02 | سياق عقد غير مصرح | `ContextBuilderTest`, `AuthorizationTest` |
| M-03 | قسم محظور في السياق | [GAP] |

---

## 7) استقرار

| ID | السيناريو | تغطية |
|----|-----------|--------|
| S-01 | إعادة محاولة OpenAI 429/503 | `OpenAIResponsesClientTest` |
| S-02 | Circuit breaker | `CircuitBreakerTest` |
| S-03 | `SmartRateLimiter` حسب الدور | `SmartRateLimiterTest::test_get_limit_matches_configured_role_cap` |
| S-04 | `throttle:ai-assistant` | [GAP] اختبار تكامل قد يكون flaky |
| S-05 | ميزانية يومية توكنات | [GAP] يحتاج `per_user_daily_tokens` > 0 |

---

## 8) أمان

| ID | السيناريو | تغطية |
|----|-----------|--------|
| A-01 | منع أداة بدون صلاحية | `AiToolsPermissionTest`, `ToolRegistryGatesTest` |
| A-02 | إخفاء PII | `PiiRedactionMiddlewareTest` |
| A-03 | وصول مستندات RAG لمالك فقط | `DocumentControllerTest` |
| A-04 | `ai/knowledge` لـ admin فقط | `AssistantKnowledgeTest` (403 لغير admin) |
| A-05 | تزييف دور | [GAP] — يعتمد على Sanctum + DB |

---

## ملخص التغطية الناقصة (أولوية)

1. **Orchestrator متعدد الأدوات / فشل أداة** — اختبارات وحدة مع `RakizAiOrchestrator` وهمي.
2. **Throttle `ai-assistant`** — اختبار تكامل قد يكون flaky؛ غير مضاف.
3. **ميزانية التوكن اليومية** — يحتاج `AI_DAILY_TOKEN_BUDGET` > 0 وسيناريو استهلاك.
4. **استثناء داخل أداة / timeout** — يحتاج mock لطبقة الشبكة أو أداة وهمية.
5. **حقن أوامر متقدّم** — سلوك النموذج؛ التغطية الحالية: `PiiRedactionMiddlewareTest`.

---

## الملفات المضافة/المحدثة لهذه الجولة

- `tests/AI_SCENARIO_MATRIX.md` (هذا الملف)
- `tests/Feature/AI/AiScenarioMatrixFeatureTest.php`
- `tests/Unit/AI/ToolRegistryEdgeCasesTest.php`
- `tests/Unit/AI/SmartRateLimiterTest.php`
