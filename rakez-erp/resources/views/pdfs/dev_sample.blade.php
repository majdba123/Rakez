@extends('layouts.pdf')

@php
    /** @var string $contract_id */
    /** @var list<array{label: string, value: string}> $rows */
    /** @var string $generated_at */
@endphp

@section('title', 'معاينة تطوير — ' . $contract_id)

@section('content')
    <p class="doc-title">معاينة قالب PDF (بيانات تجريبية)</p>
    <p class="doc-title-en" style="direction: ltr;">Dev PDF sample — dummy data</p>
    <p class="doc-subtitle">
        معرف تجريبي: <span class="ltr">{{ $contract_id }}</span>
        — {{ $generated_at }}
    </p>

    <p class="section-title">جدول الحقول</p>
    <table class="info-table">
        @foreach ($rows as $row)
            <tr>
                <td style="width: 38%;">{{ $row['label'] }}</td>
                <td style="word-break: break-word;">{{ $row['value'] }}</td>
            </tr>
        @endforeach
    </table>

    <p class="auto-msg">مسار تطوير محلي فقط — غير مفعّل في الإنتاج</p>
@endsection
