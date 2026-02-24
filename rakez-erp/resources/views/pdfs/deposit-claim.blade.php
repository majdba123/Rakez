@extends('layouts.pdf')

@section('title', 'مطالبة عربون - ' . $deposit->id)

@section('extra-styles')
    .amount-highlight { text-align: center; margin: 20px 0; padding: 15px; border: 2px solid #1B2A4A; background-color: #f0f4f8; }
    .amount-highlight .amount-val { font-size: 28px; font-weight: bold; color: #1B2A4A; }
    .amount-highlight .amount-label { font-size: 12px; color: #666; margin-bottom: 5px; }
@endsection

@section('content')
    <p class="doc-title">مطالبة عربون</p>
    <p class="doc-subtitle">رقم العربون: {{ $deposit->id }} | تاريخ الإصدار: {{ $generated_at }}</p>

    <div class="amount-highlight">
        <div class="amount-label">المبلغ / Amount</div>
        <div class="amount-val">{{ number_format($deposit->amount, 2) }} ريال</div>
    </div>

    <p class="section-title">&#9670; معلومات العربون</p>
    <table class="info-table">
        <tr>
            <td>المشروع</td>
            <td>{{ $deposit->contract->project_name ?? 'غير محدد' }}</td>
        </tr>
        @if($deposit->contractUnit)
        <tr>
            <td>الوحدة</td>
            <td>{{ $deposit->contractUnit->unit_type }} - {{ $deposit->contractUnit->unit_number }}</td>
        </tr>
        @endif
        <tr>
            <td>مصدر العمولة</td>
            <td>
                @if($deposit->commission_source === 'owner') المالك
                @elseif($deposit->commission_source === 'buyer') المشتري
                @else غير محدد
                @endif
            </td>
        </tr>
        <tr>
            <td>طريقة الدفع</td>
            <td>{{ $deposit->payment_method }}</td>
        </tr>
        <tr>
            <td>تاريخ الدفع</td>
            <td>{{ $deposit->payment_date }}</td>
        </tr>
        <tr>
            <td>الحالة</td>
            <td>
                <span class="status-badge status-{{ $deposit->status }}">
                    @if($deposit->status === 'pending') معلق
                    @elseif($deposit->status === 'received') مستلم
                    @elseif($deposit->status === 'confirmed') مؤكد
                    @elseif($deposit->status === 'refunded') مسترد
                    @endif
                </span>
            </td>
        </tr>
    </table>

    @if($deposit->confirmedBy)
    <p class="section-title">&#9670; معلومات التأكيد</p>
    <table class="info-table">
        <tr>
            <td>تم التأكيد بواسطة</td>
            <td>{{ $deposit->confirmedBy->name }}</td>
        </tr>
        <tr>
            <td>تاريخ التأكيد</td>
            <td>{{ $deposit->confirmed_at }}</td>
        </tr>
    </table>
    @endif

    @if($deposit->refund_reason)
    <p class="section-title">&#9670; معلومات الاسترداد</p>
    <table class="info-table">
        <tr>
            <td>سبب الاسترداد</td>
            <td>{{ $deposit->refund_reason }}</td>
        </tr>
        <tr>
            <td>تاريخ الاسترداد</td>
            <td>{{ $deposit->refunded_at }}</td>
        </tr>
    </table>
    @endif

    @if($deposit->notes)
    <p class="section-title">&#9670; ملاحظات</p>
    <p style="font-size: 10px; padding: 5px; background: #f9f9f9; border: 1px solid #eee;">{{ $deposit->notes }}</p>
    @endif

    <p class="auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقاري | {{ $generated_at }}</p>
@endsection
