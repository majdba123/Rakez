# In Filament / Out of Filament Matrix

## قاعدة الحسم
هذه الوثيقة تستخدم بنية الـ sidebar الحالية كدليل أعمال، ثم تحدد بدقة ما يدخل Filament وما يبقى خارجه.

المبدأ:
- ما يدخل Filament = حوكمة، رقابة، اعتماد، مؤشرات، CRUD إداري محدود للبيانات المرجعية أو الحساسة
- ما يبقى خارج Filament = التشغيل اليومي، الشاشات الشخصية، والتدفقات عالية التكرار

## Canonicalization
قبل التحويل تم توحيد بعض المسميات المتكررة:

| التاب الحالي | الاسم الإداري المعتمد داخل الحزمة |
|---|---|
| `Task Management` | `Task Oversight` داخل Filament، بينما التشغيل اليومي يبقى خارجه |
| `Team`, `Sales Team`, `Team Management`, `HR Teams` | تفصل بحسب القسم: `Teams Governance`, `Sales Team Oversight`, `HR Teams Oversight` |
| `Projects`, `Project Management`, `Sales Projects`, `Marketing Projects` | لا تعامل كعنصر واحد؛ كل قسم له تمثيل إداري مستقل |
| `Dashboard` العام وDashboards القطاعية | Dashboard عام للحوكمة + Dashboards قطاعية حسب القسم |
| `My Requests`, `Exclusive Project Request` | الأول شخصي تشغيلي خارج Filament، والثاني يدخل كمسار اعتماد/مراجعة |

## System / Administration
| التاب الحالي | يدخل Filament؟ | التمثيل الإداري | الوضع داخل Filament | يبقى خارج Filament؟ | المرحلة |
|---|---|---|---|---|---|
| Dashboard | نعم | `Page + Widgets` | رقابي | لا | MVP |
| Notifications | نعم | `Page` | رقابي/إداري | جزء التشغيل الفردي يبقى خارجه | MVP |
| Project Management | نعم | `Group: العقود والمشاريع` + `Overview/Review Pages` | رقابي | التشغيل اليومي يبقى خارجه | لاحقًا |
| Analytics | نعم | `Page + Widgets` | رقابي | لا | MVP |
| Contracts | نعم | `Resource/Page` | رقابة واعتماد | إنشاء وتشغيل اليومي يبقى خارجه | لاحقًا |
| Developers | نعم | `Read-only Resource` | رقابي/مرجعي | لا | لاحقًا |
| Team Management | نعم | `Resource` | إداري | لا | MVP عبر governance/HR لاحقًا |
| User Management | نعم | `Resource` | إداري كامل | لا | MVP |
| Task Management | نعم جزئيًا | `Read-only Oversight Page` | رقابي فقط | نعم، التشغيل اليومي يبقى خارج Filament | لاحقًا |
| Agents | نعم | `Resource/Page` | إداري محدود | لا | لاحقًا |
| Knowledge Base | نعم | `Resource/Page` | إداري/حوكمي | لا | لاحقًا |
| Commissions and Deposits | نعم | `Accounting Oversight Pages` | رقابي واعتمادي | التشغيل اليومي يبقى خارجه | بعد MVP |
| Fetch Projects | نعم | `Sync/Integration Page` | إداري محدود | لا | لاحقًا |
| Reservations | نعم جزئيًا | `Oversight Page` | رقابي | نعم | لاحقًا |
| Sold Units | نعم جزئيًا | `Read-only Accounting Oversight` | رقابي | نعم | بعد MVP |
| Accounts | نعم | `Resource/Page` | إداري | لا | بعد MVP |
| Image Approval | نعم | `Approval Page` | اعتماد | لا | لاحقًا |

## Sales
| التاب الحالي | يدخل Filament؟ | التمثيل الإداري | الوضع داخل Filament | يبقى خارج Filament؟ | المرحلة |
|---|---|---|---|---|---|
| Sales Dashboard | نعم | `Widgets/Page` | رقابي | لا | لاحقًا |
| Sales Projects | نعم جزئيًا | `Read-only Page` | رقابي | نعم | لاحقًا |
| Unit Search | نعم جزئيًا | `Investigative Read-only Page` | رقابي | البحث التشغيلي يبقى خارجه | لاحقًا |
| Sales Reservations | نعم جزئيًا | `Review Page` | مراجعة واستثناءات | نعم | لاحقًا |
| Sales Targets | نعم | `Resource + Widgets` | إداري/رقابي | الأهداف الشخصية تبقى خارجه | لاحقًا |
| Team Targets / My Targets | نعم جزئيًا | `Targets Oversight` | رقابي | `My Targets` يبقى خارجه | لاحقًا |
| Sales Team | نعم | `Overview Page` | رقابي | لا | لاحقًا |
| Team | نعم جزئيًا | `Sales Team Oversight` | رقابي | الاستخدام التشغيلي يبقى خارجه | لاحقًا |
| Attendance | نعم جزئيًا | `Analytics Page` | رقابي | نعم | لاحقًا |
| Project Attendance Schedule | نعم جزئيًا | `Schedule Oversight` | رقابي | نعم | لاحقًا |
| My Attendance | لا | خارج Filament | شخصي تشغيلي | نعم | خارج النطاق |
| Project Attendance Management | نعم جزئيًا | `Oversight/Review Page` | رقابي | الإدارة اليومية تبقى خارجه | لاحقًا |

## HR
| التاب الحالي | يدخل Filament؟ | التمثيل الإداري | الوضع داخل Filament | يبقى خارج Filament؟ | المرحلة |
|---|---|---|---|---|---|
| HR Dashboard | نعم | `Widgets/Page` | رقابي | لا | لاحقًا |
| HR Teams | نعم | `Resource/Page` | إداري | لا | لاحقًا |
| Marketers Performance | نعم | `Reports/Page` | رقابي | لا | لاحقًا |
| HR Users | نعم | `Users Resource` مع فلاتر HR | إداري | لا | MVP عبر governance ثم لاحقًا بتخصيص HR |
| HR Reports | نعم | `Reports Page` | رقابي | لا | لاحقًا |
| Reports | نعم | `Reports Page` | رقابي | لا | لاحقًا |
| User Management | نعم | `Users Resource` | إداري | لا | MVP |

## Credit
| التاب الحالي | يدخل Filament؟ | التمثيل الإداري | الوضع داخل Filament | يبقى خارج Filament؟ | المرحلة |
|---|---|---|---|---|---|
| Credit Dashboard | نعم | `Page + Widgets` | رقابي | لا | MVP |
| Credit Notifications | نعم | `Page` | رقابي | جزء التنبيهات التشغيلية يبقى خارجه | MVP |
| Credit Booking Management | نعم جزئيًا | `Review Page` | مراجعة واعتماد | نعم | MVP |
| Booking Management | نعم جزئيًا | `Review Page` | مراجعة واعتماد | نعم | MVP |
| Claim Files and Title Transfer | نعم | `Approval Page` | اعتماد حساس | لا | MVP |
| Claim File / Title Transfer | نعم | `Approval Page` | اعتماد حساس | لا | MVP |

## Accounting / Finance
| التاب الحالي | يدخل Filament؟ | التمثيل الإداري | الوضع داخل Filament | يبقى خارج Filament؟ | المرحلة |
|---|---|---|---|---|---|
| Accounting Notifications | نعم | `Page` | رقابي | جزء التشغيل يبقى خارجه | بعد MVP |
| Sold Units Accounting | نعم | `Reconciliation Page` | رقابي واعتمادي | نعم | بعد MVP |
| Sold Units | نعم جزئيًا | `Read-only Oversight` | رقابي | نعم | بعد MVP |
| Deposit and Follow-up | نعم جزئيًا | `Oversight Page` | رقابي | المتابعة اليومية تبقى خارجه | بعد MVP |
| Salaries and Commission Distribution | نعم | `Approval Page` | اعتماد حساس | لا | بعد MVP |
| Commissions and Deposits | نعم | `Approval + Oversight Pages` | رقابي واعتمادي | نعم | بعد MVP |
| Accounts | نعم | `Resource/Page` | إداري | لا | بعد MVP |
| Developers View | نعم | `Read-only Page` | رقابي | لا | بعد MVP |

## Marketing
| التاب الحالي | يدخل Filament؟ | التمثيل الإداري | الوضع داخل Filament | يبقى خارج Filament؟ | المرحلة |
|---|---|---|---|---|---|
| Marketing Dashboard | نعم | `Widgets/Page` | رقابي | لا | لاحقًا |
| Marketing Projects | نعم جزئيًا | `Overview Page` | رقابي | التشغيل اليومي يبقى خارجه | لاحقًا |
| Developer Plan | نعم جزئيًا | `Review Page` | رقابي/اعتمادي | الإدخال اليومي يبقى خارجه | لاحقًا |
| Employee Plans | نعم جزئيًا | `Review Page` | رقابي/اعتمادي | الإدخال اليومي يبقى خارجه | لاحقًا |
| Marketing Reports | نعم | `Reports Page` | رقابي | لا | لاحقًا |
| My Performance | لا | خارج Filament | شخصي تشغيلي | نعم | خارج النطاق |

## Inventory
| التاب الحالي | يدخل Filament؟ | التمثيل الإداري | الوضع داخل Filament | يبقى خارج Filament؟ | المرحلة |
|---|---|---|---|---|---|
| Inventory | نعم | `Overview Page + KPIs` | رقابي/إداري محدود | التشغيل اليومي يبقى خارجه | لاحقًا |

## AI / Knowledge / Requests / Personal
| التاب الحالي | يدخل Filament؟ | التمثيل الإداري | الوضع داخل Filament | يبقى خارج Filament؟ | المرحلة |
|---|---|---|---|---|---|
| My Requests | لا | خارج Filament | شخصي تشغيلي | نعم | خارج النطاق |
| Exclusive Project Request | نعم | `Approval/Review Page` | رقابي واعتمادي | لا | لاحقًا |
| AI Assistant | لا | خارج Filament | تشغيلي شخصي | نعم | خارج النطاق |
| AI | نعم جزئيًا | `AI Governance Pages` | رقابي/حوكمي | التفاعل اليومي يبقى خارجه | لاحقًا |
| Knowledge Base | نعم | `Resource/Page` | حوكمي | لا | لاحقًا |
| Chat | لا | خارج Filament | تشغيلي | نعم | خارج النطاق |
| Profile | لا | خارج Filament | شخصي | نعم | خارج النطاق |
| Projects (Editor view) | نعم جزئيًا | `Projects Oversight` | رقابي | التشغيل اليومي يبقى خارجه | لاحقًا |
| Teams (Editor view) | لا | خارج Filament | تشغيلي | نعم | خارج النطاق |
| Evaluations | نعم جزئيًا | `Review Page` | رقابي | الاستخدام التشغيلي يبقى خارجه | لاحقًا |

## القرار النهائي للحد الفاصل
يدخل Filament فقط إذا كان العنصر:
- يخص الحوكمة
- أو الرقابة
- أو الاعتماد
- أو التدقيق
- أو Master / Reference Data إداري

ويبقى خارج Filament إذا كان العنصر:
- شخصيًا
- تشغيليًا يوميًا
- عالي التكرار
- قائمًا أصلًا على workflow تشغيلي مستقر في الـ ERP الحالي
