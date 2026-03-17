# Fix: mapStatusForApi is not defined (ContractsView.vue)

السماحية: الأدمن ومدير إدارة المشاريع فقط (`contracts.view_all`).

## المشكلة
```
ReferenceError: mapStatusForApi is not defined at fetchContracts (ContractsView.vue:199:22)
```

## الحل 1 – استيراد الدالة (إن كان مشروع Vue داخل هذا المستودع)

في `ContractsView.vue` في الـ `<script>`:

```js
import { mapStatusForApi } from '@/utils/contractStatusMap'; // أو المسار المناسب
```

الدالة موجودة في: `resources/js/utils/contractStatusMap.js`

---

## الحل 2 – تعريف الدالة داخل المكون (أي مشروع Vue)

أضف الدالة في نفس الملف `ContractsView.vue` داخل `<script>` (مثلاً مع الـ methods أو كـ function في الأعلى):

```js
/**
 * Map UI status to API filter for GET /api/contracts/admin-index
 * API values: pending | approved | rejected | completed
 */
function mapStatusForApi(uiStatus) {
  if (uiStatus == null || uiStatus === '') return undefined;
  const map = {
    pending: 'pending', approved: 'approved', rejected: 'rejected',
    completed: 'completed',
    'قيد الانتظار': 'pending', 'معتمد': 'approved', 'مرفوض': 'rejected',
    'مكتمل': 'completed',
  };
  const s = String(uiStatus).trim();
  return map[s] ?? s;
}
```

ثم في `fetchContracts` استخدمها عند بناء الـ query، مثلاً:

```js
params.status = mapStatusForApi(selectedStatus);
```

قيم الـ status المقبولة في الـ API: `pending`, `approved`, `rejected`, `completed`.
