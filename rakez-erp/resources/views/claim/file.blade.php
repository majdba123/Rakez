@extends('layouts.pdf')

@php
    $claimTitle = $claim_file->isCombined() ? 'ملف مطالبة مجمع' : 'ملف مطالبة';
    $summary = $file_data['summary'] ?? [];
    $items = $file_data['items'] ?? [];
    $primaryReservation = $claim_file->reservation;
    $number = static fn ($value): string => $value === null || $value === '' ? '-' : number_format((float) $value, 2);
@endphp

@section('title', $claimTitle . ' - ' . $claim_file->id)

@section('extra-styles')
    .totals-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    .totals-table td { padding: 8px; border: 1px solid #ddd; font-size: 11px; }
    .totals-table td:first-child { width: 60%; background-color: #f5f5f5; font-weight: bold; }
    .totals-table tr:last-child td { background-color: #1B2A4A; color: #fff; font-size: 13px; font-weight: bold; }
@endsection

@section('content')
    <p class="doc-title">{{ $claimTitle }}</p>
    <p class="doc-subtitle">رقم الملف: {{ $claim_file->id }} | تاريخ الإصدار: {{ $generated_at }}</p>

    <p class="section-title">&#9670; معلومات الملف</p>
    <table class="info-table">
        <tr>
            <td>نوع الملف</td>
            <td>{{ $claim_file->isCombined() ? 'مجمع' : 'فردي' }}</td>
        </tr>
        <tr>
            <td>المولد بواسطة</td>
            <td>{{ $claim_file->generatedBy?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>المشروع</td>
            <td>{{ $summary['project_name'] ?? ($file_data['project_name'] ?? ($primaryReservation?->contract?->project_name ?? '-')) }}</td>
        </tr>
        <tr>
            <td>عدد الحجوزات</td>
            <td>{{ $claim_file->isCombined() ? count($items) : 1 }}</td>
        </tr>
        <tr>
            <td>إجمالي مبلغ المطالبة</td>
            <td>{{ $number($claim_file->total_claim_amount ?? ($summary['total_claim_amount'] ?? null)) }} AED</td>
        </tr>
    </table>

    @if (! $claim_file->isCombined())
        <p class="section-title">&#9670; بيانات الحجز</p>
        <table class="info-table">
            <tr>
                <td>رقم الحجز</td>
                <td>{{ $file_data['reservation_id'] ?? ($claim_file->sales_reservation_id ?? '-') }}</td>
            </tr>
            <tr>
                <td>العميل</td>
                <td>{{ $file_data['client_name'] ?? ($primaryReservation?->client_name ?? '-') }}</td>
            </tr>
            <tr>
                <td>رقم الوحدة</td>
                <td>{{ $file_data['unit_number'] ?? ($primaryReservation?->contractUnit?->unit_number ?? '-') }}</td>
            </tr>
            <tr>
                <td>قيمة الوحدة</td>
                <td>{{ $number($file_data['unit_price'] ?? null) }} AED</td>
            </tr>
            <tr>
                <td>نسبة العمولة</td>
                <td>{{ $number($file_data['brokerage_commission_percent'] ?? null) }}%</td>
            </tr>
            <tr>
                <td>مبلغ العربون</td>
                <td>{{ $number($file_data['down_payment_amount'] ?? null) }} AED</td>
            </tr>
        </table>
    @else
        <p class="section-title">&#9670; ملخص المطالبة المجمعة</p>
        <table class="totals-table">
            <tr>
                <td>عدد الحجوزات</td>
                <td>{{ count($items) }}</td>
            </tr>
            <tr>
                <td>إجمالي قيمة الوحدات</td>
                <td>{{ $number($summary['total_unit_price'] ?? null) }} AED</td>
            </tr>
            <tr>
                <td>إجمالي مبلغ المطالبة</td>
                <td>{{ $number($summary['total_claim_amount'] ?? null) }} AED</td>
            </tr>
        </table>
    @endif

    <p class="section-title">&#9670; عناصر المطالبة</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>رقم الحجز</th>
                <th>المشروع</th>
                <th>الوحدة</th>
                <th>العميل</th>
                <th>قيمة الوحدة</th>
                <th>العمولة %</th>
            </tr>
        </thead>
        <tbody>
            @if ($claim_file->isCombined())
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item['reservation_id'] ?? '-' }}</td>
                        <td>{{ $item['project_name'] ?? '-' }}</td>
                        <td>{{ $item['unit_number'] ?? '-' }}</td>
                        <td>{{ $item['client_name'] ?? '-' }}</td>
                        <td>{{ $number($item['unit_price'] ?? null) }}</td>
                        <td>{{ $number($item['brokerage_commission_percent'] ?? null) }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td>{{ $file_data['reservation_id'] ?? ($claim_file->sales_reservation_id ?? '-') }}</td>
                    <td>{{ $file_data['project_name'] ?? ($primaryReservation?->contract?->project_name ?? '-') }}</td>
                    <td>{{ $file_data['unit_number'] ?? ($primaryReservation?->contractUnit?->unit_number ?? '-') }}</td>
                    <td>{{ $file_data['client_name'] ?? ($primaryReservation?->client_name ?? '-') }}</td>
                    <td>{{ $number($file_data['unit_price'] ?? null) }}</td>
                    <td>{{ $number($file_data['brokerage_commission_percent'] ?? null) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    @if (! empty($claim_file->notes))
        <p class="section-title">&#9670; ملاحظات</p>
        <div class="summary-box">
            <p>{{ $claim_file->notes }}</p>
        </div>
    @endif

    <p class="auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقاري | {{ $generated_at }}</p>
@endsection
