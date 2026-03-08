@extends('layouts.pdf')

@section('title', 'الحملات الإعلانية على المنصات الإلكترونية')

@section('extra-styles')
    <style>
        .dist-doc-title { font-size: 18px; font-weight: bold; color: #1B2A4A; text-align: center; margin: 5px 0 3px 0; }
        .dist-doc-subtitle { font-size: 13px; color: #333; text-align: right; margin: 0 0 15px 0; }
        .dist-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .dist-table th, .dist-table td { padding: 8px 10px; border: 1px solid #ddd; font-size: 11px; text-align: center; }
        .dist-table th { background-color: #6B5344; color: #fff; font-weight: bold; }
        .dist-table tbody tr:nth-child(even) { background-color: #f9f8f6; }
        .dist-table tbody tr:nth-child(odd) { background-color: #fff; }
        .dist-table .total-row { background-color: #6B5344 !important; color: #fff; font-weight: bold; }
        .dist-table .total-row td { border-color: #5a4537; }
        .dist-notes { margin-top: 20px; padding-right: 10px; }
        .dist-notes ul { margin: 8px 0; padding-right: 22px; }
        .dist-notes li { margin: 4px 0; font-size: 11px; color: #333; }
    </style>
@endsection

@section('content')
    <p class="dist-doc-title">الحملات الإعلانية على المنصات الإلكترونية</p>
    <p class="dist-doc-subtitle">خطة اسبوعية مرنة :</p>

    @php
        $rows = $distribution['rows'] ?? [];
        $totalClicks = (int) ($distribution['total_clicks'] ?? 0);
        $totalImpressions = (int) ($distribution['total_impressions'] ?? 0);
    @endphp
    <table class="dist-table">
        <thead>
            <tr>
                <th style="width: 8%;">م</th>
                <th style="width: 42%;">المنصة الإعلانية</th>
                <th style="width: 25%;">النقرات</th>
                <th style="width: 25%;">المشاهدات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                <td>{{ str_pad((int)($row['index'] ?? 0), 2, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $row['platform_ar'] ?? '' }}</td>
                <td class="ltr">{{ number_format((int)($row['clicks'] ?? 0)) }}</td>
                <td class="ltr">{{ number_format((int)($row['impressions'] ?? 0)) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td></td>
                <td>الإجمالي</td>
                <td class="ltr">{{ number_format($totalClicks) }}</td>
                <td class="ltr">{{ number_format($totalImpressions) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="dist-notes">
        <ul>
            <li>الأرقام مرنة بشكل أسبوعي</li>
            <li>سيتم تفعيل حملات Sales - Leads - Awareness - Traffic -</li>
        </ul>
    </div>

    <p class="auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقاري</p>
@endsection
