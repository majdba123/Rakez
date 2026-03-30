# المرحلة 1 — اكتشاف النظام الفعلي (مستخرج من الكود)

**المصدر:** `routes/api.php`، `config/ai_assistant.php`، `config/ai_capabilities.php`، `app/Services/AI/ToolRegistry.php`، ووثيقة `docs/VIEW-TO-API-PERMISSIONS.md`.

**بادئة الـ API:** المسارات أدناه تُفترض تحت `/api` (Laravel default `api`).

---

## 1) مسارات المساعد / الذكاء الاصطناعي / الأدوات

| المسار | الطريقة | حماية | ملاحظة |
|--------|---------|--------|--------|
| `/ai/ask` | POST | `auth:sanctum` + `throttle:ai-assistant` | `AIAssistantController::ask` |
| `/ai/chat` | POST | نفس | يدعم `stream` (SSE) |
| `/ai/conversations` | GET | نفس | جلسات المحادثة |
| `/ai/conversations/{sessionId}` | DELETE | نفس | حذف جلسة |
| `/ai/sections` | GET | نفس | أقسام المساعد حسب الصلاحيات |
| `/ai/tools/chat` | POST | نفس | أوركسترا الأدوات (Rakiz) — المفضّل |
| `/ai/tools/stream` | POST | نفس | SSE لرد واحد |
| `/ai/v2/chat` | POST | نفس | alias لـ `/ai/tools/chat` |
| `/ai/v2/stream` | POST | نفس | alias لـ `/ai/tools/stream` |
| `/ai/documents/*` | متعدد | نفس | رفع/فهرسة/بحث RAG |
| `/ai/calls/*` | متعدد | `auth:sanctum` + `role:admin\|sales\|sales_leader\|marketing` + `permission:ai-calls.manage` | ليس شات المساعد العام |
| `/ai/knowledge/*` | متعدد | `auth:sanctum` + `role:admin` | قاعدة معرفة للمساعد |
| `/ai/assistant/chat` | POST | `auth:sanctum` + `throttle` غير مذكور على هذا السطر | `AssistantChatController` — مساعد منفصل مع معرفة محلية |
| `/webhooks/twilio/*` | POST | توقيع Twilio | لا مصادقة مستخدم |

**الاستنتاج:** مسارات الشات الأساسية للـ ERP هي `POST /api/ai/chat`، `POST /api/ai/ask`، و`POST /api/ai/tools/chat` (و`/api/ai/assistant/chat` لمسار آخر).

---

## 2) الأدوار والصلاحيات (bootstrap)

**الملف:** `config/ai_capabilities.php` → `bootstrap_role_map`.

الأدوار المستخدمة في الاختبارات المكافئة للإنتاج:  
`admin`, `project_management`, `editor`, `developer`, `marketing`, `sales`, `sales_leader`, `hr`, `credit`, `accounting`, `inventory`, `default`, `accountant`.

**صلاحية استخدام المساعد:** `use-ai-assistant` (مطلوبة لـ `POST /api/ai/tools/chat` و `AssistantChatController`).

**مساعد v2 (أدوات):** `AiV2Controller` يتحقق صراحة `use-ai-assistant` ويعيد 403 عند عدم التوفر.

---

## 3) الأدوات المسجّلة (ToolRegistry)

أسماء الأدوات: `tool_search_records`, `tool_get_lead_summary`, `tool_get_project_summary`, `tool_get_contract_status`, `tool_kpi_sales`, `tool_explain_access`, `tool_rag_search`, `tool_campaign_advisor`, `tool_hiring_advisor`, `tool_finance_calculator`, `tool_marketing_analytics`, `tool_sales_advisor`, `tool_smart_distribution`, `tool_employee_recommendation`, `tool_campaign_funnel`, `tool_roas_optimizer`, `tool_ai_call_status`.

**بوابة صلاحية مثال (من الإعداد):** `config('ai_assistant.v2.tool_gates')`  
`tool_kpi_sales` → `permission: sales.dashboard.view`.

---

## 4) حدود وإعدادات (budget, tokens, أقسام)

| المفتاح | المصدر | معنى |
|---------|--------|------|
| `ai_assistant.openai.max_output_tokens` | `config/ai_assistant.php` | حد مخرجات النموذج (v1) |
| `ai_assistant.v2.openai.max_output_tokens` | نفس | v2 / أدوات |
| `ai_assistant.v2.tool_loop.max_tool_calls` | نفس | عدد دورات استدعاء الأدوات |
| `ai_assistant.budgets.per_user_daily_tokens` | نفس | 0 = معطّل |
| `ai_assistant.rate_limits.per_minute` | نفس | throttle عام |
| `ai_assistant.smart_rate_limits` | نفس | حدود حسب الدور (admin, sales, …) |
| `ai_assistant.tools.sections` | نفس | `marketing`, `sales`, `finance`, `hr` |
| `ai_assistant.rag.*` | نفس | فهرسة وبحث دلالي |

---

## 5) ما لا يُختبر عبر الـ API فقط

- **Twilio webhooks:** لا يُعاد إنتاج توقيع Twilio حقيقي في الاختبار الآلي الاعتيادي؛ يُذكر في التقرير كـ "خارجي غير متاح في الاختبار المحلي دون إعدادات Twilio".

- **OpenAI:** الاختبارات الحقيقية تتطلب `OPENAI_API_KEY` و `AI_REAL_TESTS=true` في `.env` — وإلا تُتخطى (`TestsWithRealOpenAiConnection`).
