<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>عقد - {{ $contract->project_name ?? 'Contract' }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; direction: rtl; text-align: right; padding: 20px; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .section { margin-bottom: 20px; }
        .section-title { background: #f8f9fa; padding: 8px; font-weight: bold; margin-bottom: 8px; }
        .info-row { padding: 4px 0; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="header">
        <h1>تفاصيل العقد / Contract Summary</h1>
        <p>{{ $contract->project_name }}</p>
    </div>
    <div class="section">
        <div class="section-title">معلومات المشروع</div>
        <div class="info-row">اسم المشروع: {{ $contract->project_name }}</div>
        <div class="info-row">المطور: {{ $contract->developer_name }}</div>
        <div class="info-row">المدينة: {{ $contract->city }}</div>
        <div class="info-row">الحي: {{ $contract->district }}</div>
        <div class="info-row">الحالة: {{ $contract->status }}</div>
        @if($contract->notes)
        <div class="info-row">ملاحظات: {{ $contract->notes }}</div>
        @endif
    </div>
    <div class="section">
        <div class="section-title">التاريخ</div>
        <div class="info-row">تاريخ الإنشاء: {{ $contract->created_at?->format('Y-m-d') }}</div>
    </div>
</body>
</html>
