@extends('layouts.pdf')

@section('title', 'مطالبة عمولة - ' . $commission->id)

@section('extra-styles')
    .totals-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    .totals-table td { padding: 8px; border: 1px solid #ddd; font-size: 11px; }
    .totals-table td:first-child { width: 60%; background-color: #f5f5f5; font-weight: bold; }
    .totals-table tr:last-child td { background-color: #1B2A4A; color: #fff; font-size: 13px; font-weight: bold; }
@endsection

@section('content')
    <p class="doc-title">مطالبة عمولة</p>
    <p class="doc-subtitle">رقم العمولة: {{ $commission->id }} | تاريخ الإصدار: {{ $generated_at }}</p>

    <p class="section-title">&#9670; معلومات العمولة</p>
    <table class="info-table">
        <tr>
            <td>سعر البيع النهائي</td>
            <td>{{ number_format($commission->final_selling_price, 2) }} ريال</td>
        </tr>
        <tr>
            <td>نسبة العمولة</td>
            <td>{{ $commission->commission_percentage }}%</td>
        </tr>
        <tr>
            <td>الحالة</td>
            <td>
                <span class="status-badge status-{{ $commission->status }}">
                    @if($commission->status === 'pending') معلق
                    @elseif($commission->status === 'approved') معتمد
                    @elseif($commission->status === 'paid') مدفوع
                    @endif
                </span>
            </td>
        </tr>
    </table>

    <p class="section-title">&#9670; توزيع العمولة</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>المستلم</th>
                <th>النوع</th>
                <th>النسبة</th>
                <th>المبلغ</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
            @foreach($distributions as $dist)
            <tr>
                <td>
                    @if($dist->recipient)
                        {{ $dist->recipient->name }}
                    @else
                        {{ $dist->external_marketer_name ?? 'غير محدد' }}
                    @endif
                </td>
                <td>{{ $dist->type }}</td>
                <td>{{ $dist->percentage }}%</td>
                <td>{{ number_format($dist->amount, 2) }} ريال</td>
                <td>
                    <span class="status-badge status-{{ $dist->status }}">
                        @if($dist->status === 'pending') معلق
                        @elseif($dist->status === 'approved') معتمد
                        @elseif($dist->status === 'rejected') مرفوض
                        @elseif($dist->status === 'paid') مدفوع
                        @endif
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p class="section-title">&#9670; الإجماليات</p>
    <table class="totals-table">
        <tr>
            <td>المبلغ الإجمالي</td>
            <td>{{ number_format($commission->total_amount, 2) }} ريال</td>
        </tr>
        <tr>
            <td>ضريبة القيمة المضافة (15%)</td>
            <td>{{ number_format($commission->vat, 2) }} ريال</td>
        </tr>
        <tr>
            <td>مصاريف التسويق</td>
            <td>{{ number_format($commission->marketing_expenses, 2) }} ريال</td>
        </tr>
        <tr>
            <td>رسوم البنك</td>
            <td>{{ number_format($commission->bank_fees, 2) }} ريال</td>
        </tr>
        <tr>
            <td>صافي العمولة</td>
            <td>{{ number_format($commission->net_amount, 2) }} ريال</td>
        </tr>
    </table>

    <p class="auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقاري | {{ $generated_at }}</p>
@endsection
