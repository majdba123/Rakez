# المرحلة 2 — مصفوفة التغطية (Role × Endpoint × Capability × Tool × Scenario)

**صيغة التوقع:** لكل خلية يُسجَّل: النتيجة التقنية (HTTP)، النتيجة الوظيفية، تصنيف الجودة (انظر المرحلة 5 في طلب المستخدم).

## أعمدة ثابتة

| العمود | الوصف |
|--------|--------|
| Role | أحد أدوار `bootstrap_role_map` |
| Endpoint | مسار HTTP تحت `/api` |
| Capability | قسم، صلاحية، أو سلوك متوقع |
| Tool | أداة أو `—` |
| Scenario | وصف سيناريو قصير |
| Expected | تقني/وظيفي/جودة |
| Quality threshold | حد أدنى مقبول للجودة (مثلاً: هيكل JSON، عدم تسريب، إجابة مباشرة) |

## صفوف (عينة مُولَّدة من الكود — لا تغطي كل التوليفات)

| Role | Endpoint | Capability | Tool | Scenario | Expected | Quality threshold |
|------|----------|------------|------|----------|----------|-------------------|
| `*` | `POST /ai/tools/chat` | `use-ai-assistant` | — | بدون مصادقة | 401 | — |
| `*` | `POST /ai/tools/chat` | `use-ai-assistant` | — | مستخدم بلا `use-ai-assistant` | 403 | — |
| `*` | `POST /ai/tools/chat` | `use-ai-assistant` | — | رسالة > 16000 حرف | 422 | — |
| `marketing` | `POST /ai/tools/chat` | أدوات مسموحة | `tool_kpi_sales` **غير** في القائمة | سؤال KPI مبيعات | 200 + رد يفترض عدم تسريب أدوات خام | لا يظهر `tool_kpi_sales` كنص زائف |
| `sales` | `POST /ai/tools/chat` | أدوات مسموحة | `tool_kpi_sales` **في** القائمة | سؤال KPI | 200 + هيكل `rakiz` | `answer_markdown` + `confidence` + `access_notes` |
| `default` | `POST /ai/ask` | قسم غير مصرح | — | `section=sales` | 403 `UNAUTHORIZED_SECTION` | — |
| `admin` | `GET /ai/knowledge` | `role:admin` | — | CRUD | 200/201 حسب العملية | — |
| `marketing` | `GET /ai/knowledge` | ليس admin | — | قائمة | 403 | — |

## توسيع التغطية

- **كل الأدوار × عربي:** `tests/Integration/AI/AIArabicRolesRealE2ETest.php` (يتطلب OpenAI حقيقي).
- **مصفوفة سيناريوهات ثابتة (بدون OpenAI حقيقي حيثما أمكن):** `tests/AI_SCENARIO_MATRIX.md` و `AiScenarioMatrixFeatureTest`.
