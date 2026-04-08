@extends('layouts.pdf')

@php
    /** @var string $contract_id */
    /** @var list<array{label: string, value: string}> $rows */
    /** @var string $generated_at */
@endphp

@section('title', 'بيانات ملء العقد — عقد ' . $contract_id)

@section('content')
    <div class="doc-title-wrap">
        <p class="doc-title">بيانات ملء قالب العقد الحصري</p>
        <p class="doc-title-en" style="direction: ltr;">Exclusive contract — fill template fields</p>
        <p class="doc-subtitle">
            مرجع العقد (contract_id): <span class="ltr">{{ $contract_id }}</span>
            — تم إنشاء المستند: {{ $generated_at }}
        </p>
    </div>

    <p class="section-title first">الحقول</p>
    <table class="info-table">
        @foreach ($rows as $row)
            <tr>
                <td style="width: 38%;">{{ $row['label'] }}</td>
                <td style="word-break: break-word;">{{ $row['value'] }}</td>
            </tr>
        @endforeach
    </table>

    <p class="auto-msg">نسخة من بيانات /api/contracts/{id}/fill-data — نظام راكز</p>
@endsection
