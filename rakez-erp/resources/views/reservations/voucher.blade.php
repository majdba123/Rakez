@extends('layouts.pdf_sand_qabd')

@php
    $refCode = 'RZ' . str_pad((string) $reservation->id, 5, '0', STR_PAD_LEFT);
    $amount = (float) $reservation->down_payment_amount;
    $remainDisplay = '0';

    $payMethodAr = match ($reservation->payment_method) {
        'bank_transfer' => 'تحويل بنكي',
        'cash' => 'نقداً',
        'bank_financing' => 'تمويل بنكي',
        default => '—',
    };
    $purchaseAr = match ($reservation->purchase_mechanism) {
        'cash' => 'نقدي',
        'supported_bank' => 'عملاء بنوك معتمدة',
        'unsupported_bank' => 'عملاء بنوك غير معتمدة',
        default => '—',
    };
    $typeLines = $payMethodAr . '<br><span style="font-size:9pt;color:#4b5563;">' . e($purchaseAr) . '</span>';

    $resTypeAr = $reservation->reservation_type === 'confirmed_reservation'
        ? 'حجز مؤكد'
        : 'حجز للتفاوض';

    $contractDt = $reservation->contract_date;
    $dStr = $contractDt ? $contractDt->format('d/m/Y') : '—';

    $projectName = $project['name'] ?? '';
    $district = $project['district'] ?? '';
    $city = $project['city'] ?? '';
    $unitNo = $unit['number'] ?? '';
    $unitType = $unit['type'] ?? '';
    $area = $unit['area'] ?? '';
    $unitPrice = isset($unit['price']) ? (float) $unit['price'] : 0.0;
@endphp

@section('title', 'سند قبض حجز - ' . $reservation->id)

@section('content')
    <p class="sand-title">سند قبض — عربون حجز</p>
    <p class="sand-title-en" dir="ltr">Reservation receipt — down payment</p>
    <p class="sand-subtitle">
        رقم الحجز: <span class="ltr">{{ $reservation->id }}</span>
        — مرجع السند: <span class="ltr">{{ $refCode }}</span>
        — تاريخ العقد: {{ $dStr }}
        — أُعدّ المستند: {{ now()->format('Y-m-d H:i') }}
    </p>

    <table class="sand-meta-line" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 62%;">
                <strong>مستلم من /</strong>
                {{ $reservation->client_name }}
            </td>
            <td style="width: 38%; text-align: left; direction: ltr;">
                <strong>رقم الجوال:</strong> {{ $reservation->client_mobile }}
            </td>
        </tr>
    </table>

    <table class="sand-grid" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th style="width: 18%;">رقم المرجع</th>
                <th style="width: 22%;">النوع</th>
                <th style="width: 16%;">المبلغ</th>
                <th style="width: 16%;">المبلغ المتبقي</th>
                <th style="width: 22%;">التاريخ</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>01</td>
                <td class="sand-ref ltr">{{ $refCode }}</td>
                <td class="sand-type">{!! $typeLines !!}</td>
                <td>{{ number_format($amount, 0) }}</td>
                <td>{{ $remainDisplay }}</td>
                <td>{{ $dStr }}</td>
            </tr>
        </tbody>
    </table>

    <p class="sand-section first">وصف سند القبض</p>
    <p class="sand-desc">
        <span class="sand-desc-num">.01</span>
        عربون {{ $resTypeAr }} لشراء وحدة عقارية رقم «{{ $unitNo ?: '—' }}» من نوع «{{ $unitType ?: '—' }}» ضمن مشروع «{{ $projectName ?: '—' }}» في حي «{{ $district ?: '—' }}» بمدينة «{{ $city ?: '—' }}».
    </p>
    <p class="sand-desc">
        <span class="sand-desc-num">.02</span>
        مساحة الوحدة «{{ $area !== '' ? $area . ' م²' : '—' }}» وبسعر إجمالي قدره «{{ $unitPrice > 0 ? number_format($unitPrice, 0) . ' ريال' : '—' }}» وفق البيانات المسجلة لدى الشركة وقت إصدار السند.
    </p>
    <p class="sand-desc">
        <span class="sand-desc-num">.03</span>
        آلية الشراء: {{ $purchaseAr }} — طريقة سداد العربون: {{ $payMethodAr }}.
        @if($reservation->down_payment_status === 'refundable')
            حالة العربون: قابل للاسترداد وفق سياسة الشركة.
        @else
            حالة العربون: غير قابل للاسترداد إلا بما ينص عليه العقد لاحقاً.
        @endif
    </p>
    <p class="sand-desc">
        <span class="sand-desc-num">.04</span>
        يُعد هذا السند إيصالاً باستلام مبلغ العربون الموضح أعلاه ولا يُشكِّل بذاته عقد بيع نهائي ويبقى مشروطاً بإتمام إجراءات الحجز والموافقات النظامية.
    </p>
    @if(filled($reservation->negotiation_notes))
        <p class="sand-desc">
            <span class="sand-desc-num">.05</span>
            ملاحظات التفاوض: {{ $reservation->negotiation_notes }}
        </p>
    @endif

    <p class="sand-section">الشروط والإقرارات</p>
    <div class="sand-terms-wrap">
        <div class="sand-term">
            <span class="tn">01.</span>
            يقر العميل بأن المبالغ المدفوعة عن وحدات المشروع تُسدد لدى الشركة فقط وفي حساباتها الرسمية المعتمدة، ولا تبرأ ذمة العميل إلا بإثبات الإيداع في تلك الحسابات وفق سياسة الشركة.
        </div>
        <div class="sand-term">
            <span class="tn">02.</span>
            @if($reservation->payment_method === 'bank_transfer')
                يُسدد العربون بالتحويل البنكي إلى الحسابات الرسمية المعتمدة للشركة للمشروع فقط، ويتحمل العميل مسؤولية صحة بيانات التحويل والإثبات.
            @elseif($reservation->payment_method === 'cash')
                في حال السداد النقدي المعتمد لدى الشركة، يُعد إيصال الاستلام أو إثبات القبض الصادر من الشركة هو المعتمد.
            @else
                يلتزم العميل بإثبات السداد وفق طريقة الدفع المعتمدة لدى الشركة والمشار إليها في هذا السند.
            @endif
        </div>
        <div class="sand-term">
            <span class="tn">03.</span>
            يقر العميل باطلاعه على مخطط المشروع والمواصفات العامة المعروضة وموافقته على الحجز وفقاً لذلك، مع جواز مراجعة تفاصيل الوحدة لاحقاً في العقد.
        </div>
        <div class="sand-term">
            <span class="tn">04.</span>
            يجوز للشركة إلغاء الحجز في حال مخالفة شروط السداد أو تقديم بيانات مضللة، مع احترام السياسة المعتمدة للاسترداد إن وُجدت.
        </div>
        <div class="sand-term">
            <span class="tn">05.</span>
            تخضع العلاقة التعاقدية النهائية لنموذج عقد الشركة المعتمد لدى الجهات المختصة، وتسري أحكامه من تاريخ التوقيع عليه.
        </div>
        <div class="sand-term">
            <span class="tn">06.</span>
            أقرُّ بأنني قرأت وفهمت بنود هذا السند والشروط أعلاه ووقعت عليه طواعية من دون إكراه.
        </div>
    </div>

    <div class="sand-notice">
        يعتبر اعتماد الحجز بتوقيع العميل والإقرار بالموافقة وقراءة الشروط، وفي حال عدم إكمال الإجراءات خلال المدة التي تحددها الشركة يُعد الحجز لاغياً دون إخلال بحقوق الشركة بموجب الأنظمة.
    </div>

    <table class="sand-sig-grid" cellpadding="0" cellspacing="0">
        <tr>
            <td><strong>اسم العميل /</strong></td>
            <td><strong>التاريخ /</strong></td>
            <td><strong>توقيع العميل /</strong></td>
        </tr>
        <tr>
            <td><div class="sand-sig-line" style="font-weight: normal;">{{ $reservation->client_name }}</div></td>
            <td><div class="sand-sig-line" style="font-weight: normal;">{{ $dStr }}</div></td>
            <td><div class="sand-sig-line">&nbsp;</div></td>
        </tr>
    </table>

    <table class="sand-sig-grid" cellpadding="0" cellspacing="0" style="margin-top: 12px;">
        <tr>
            <td colspan="3" style="text-align: center; font-size: 9pt; color: #6b7280;">
                الموظف المسؤول: {{ $employee['name'] ?? '—' }}
                @if(! empty($employee['team']))
                    — {{ $employee['team'] }}
                @endif
            </td>
        </tr>
    </table>

    <p class="sand-auto-msg">سند حجز — نظام راكز العقارية | رقم الحجز {{ $reservation->id }}</p>
@endsection
