# تقرير Rubric Dataset (Real API)

**التاريخ:** 2026-03-30 11:34:09
  
**endpoint focus:** `/api/ai/tools/chat` و `/api/ai/ask`
  
**ملاحظة:** التقييم Heuristic evidence-based من النص + evidence من `ai_audit_trail` (tool_call).

### accountant — C06_ambiguous_cautious

**Endpoint:** `/api/ai/tools/chat`  
**qualityMin:** 32/50  

**Minscore:** 40/50 (80%) — جيد
  
**toolCalls:** [{"tool":"tool_sales_advisor","denied":false},{"tool":"tool_sales_advisor","denied":false}]
  
**disqualifying_flags:** []
  
**Snippet:** `لا أملك بيانات كافية لتحليل أداء مبيعاتك أو تقديم تحسينات دقيقة بدون معلومات محددة عن معدلات الإغلاق، عدد المكالمات اليومية، أو معدل التحويل للزيارات.  

نقاط البيانات المطلوبة لتحليل أفضل:
1. معدل إغلاق الصفقات (Close Rate) الحالي.
2. متوسط عدد المكالمات اليو`
### accountant — C10_allowed_department

**Endpoint:** `/api/ai/ask`  
**qualityMin:** 32/50  

**Minscore:** 40/50 (80%) — جيد
  
**toolCalls:** —
  
**disqualifying_flags:** []
  
**Snippet:** `بصفتك محاسب (accountant) في قسم General، يمكنك القيام بالمهام التالية:

1. **عرض العقود**  
   - يمكنك رؤية قائمة العقود التي تخصك.  
   - خطوات:  
     1. اذهب إلى قسم العقود (Contracts).  
     2. اختر "عرض العقود" لترى العقود الخاصة بك مع تفاصيلها مثل اسم ا`
### accountant — C02_needs_retrieval

**Endpoint:** `/api/ai/tools/chat`  
**qualityMin:** 30/50  

**Minscore:** 40/50 (80%) — جيد
  
**toolCalls:** [{"tool":"tool_search_records","denied":false},{"tool":"tool_search_records","denied":false}]
  
**disqualifying_flags:** []
  
**Snippet:** `في سجلات النظام وجدنا ليد واحد مرتبط بمنصة Snapchat:
- ليد باسم Colt Okuneva
- حالة الليد: جديد
- المصدر: Snapchat
- مخصص لمستخدم معين (ربما مسؤول مبيعات أو متابعة)

لم يتم العثور على عقود مرتبطة بـ Snapchat ضمن السجلات الحالية.

للتالي يمكن:
1. متابعة هذا الل`
