@extends('layouts.pdf')

@section('title', 'خطة تسويق المطور - ' . ($projectName ?? ''))

@section('content')
    <p class="doc-title">خطة تسويق المطور</p>
    <p class="doc-title-en">Developer Marketing Plan</p>

    <p class="section-title">&#9670; معلومات المشروع</p>
    <table class="info-table">
        <tr><td>المشروع / Project</td><td>{{ $projectName ?? '-' }}</td></tr>
        <tr><td>رقم العقد / Contract ID</td><td>{{ $contractId }}</td></tr>
    </table>

    @if(!empty($plan))
    <p class="section-title">&#9670; تفاصيل الخطة / Plan Details</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>البيان</th>
                <th>القيمة</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plan as $key => $value)
                @if(!is_array($value) && !is_null($value))
                <tr>
                    <td>{{ str_replace('_', ' ', $key) }}</td>
                    <td>{{ $value }}</td>
                </tr>
                @endif
            @endforeach
        </tbody>
    </table>
    @endif

    <p class="auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقاري</p>
@endsection
