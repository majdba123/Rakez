<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@yield('title')</title>
    <style>
        @page {
            margin: 22mm 14mm 38mm 14mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'dejavusans', 'dejavu sans', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 11pt;
            color: #111827;
            margin: 0;
            padding: 0;
            line-height: 1.65;
        }

        .pdf-letterhead {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 20px 0;
        }
        .pdf-letterhead-bar {
            background-color: #1B2A4A;
            height: 5px;
            padding: 0;
            font-size: 1px;
            line-height: 1px;
        }
        .pdf-letterhead-body {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-top: none;
            padding: 16px 20px;
            text-align: center;
        }

        .doc-title-wrap {
            margin: 0 0 18px 0;
            padding: 0 0 14px 0;
            border-bottom: 2px solid #1B2A4A;
        }
        .doc-title {
            text-align: center;
            font-size: 17pt;
            font-weight: bold;
            color: #1B2A4A;
            margin: 0 0 6px 0;
            letter-spacing: 0.02em;
        }
        .doc-title-en {
            text-align: center;
            font-size: 10pt;
            color: #64748b;
            margin: 0 0 10px 0;
            font-weight: normal;
        }
        .doc-subtitle {
            text-align: center;
            font-size: 9.5pt;
            color: #475569;
            margin: 0;
            padding: 10px 14px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            line-height: 1.55;
        }

        .section-title {
            font-size: 11.5pt;
            font-weight: bold;
            color: #fff;
            background-color: #1B2A4A;
            margin: 22px 0 0 0;
            margin-bottom: 0;
            padding: 9px 14px;
            border: 1px solid #142038;
        }
        .section-title.first {
            margin-top: 6px;
        }
        .section-title::before {
            content: '\2756\00a0';
            font-weight: bold;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 18px 0;
            border: 1px solid #94a3b8;
            border-top: none;
        }
        .info-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10pt;
            vertical-align: top;
            line-height: 1.55;
        }
        .info-table tr:last-child td {
            border-bottom: none;
        }
        .info-table td:first-child {
            width: 34%;
            background-color: #f1f5f9;
            font-weight: bold;
            color: #1e293b;
            border-left: 1px solid #e2e8f0;
        }
        .info-table td:last-child {
            background-color: #ffffff;
            color: #334155;
        }
        .info-table td:last-child.ltr {
            word-break: break-word;
            font-size: 9.5pt;
        }

        /* وحدات — نفس شبكة سند القبض الرسمية */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 18px 0;
            border: 1.2px solid #374151;
        }
        .data-table th {
            background-color: #e5e7eb;
            color: #111827;
            padding: 9px 8px;
            border: 1px solid #4b5563;
            font-size: 10pt;
            text-align: center;
            font-weight: bold;
        }
        .data-table td {
            padding: 9px 8px;
            border: 1px solid #9ca3af;
            text-align: center;
            font-size: 10pt;
            background-color: #ffffff;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f9fafb;
        }

        .empty-msg {
            color: #94a3b8;
            font-style: italic;
            padding: 12px;
            font-size: 10pt;
        }

        .footer-wrap {
            border-top: 2px solid #1B2A4A;
            padding-top: 8px;
            margin-top: 6px;
            background-color: #f8fafc;
        }
        .footer-tbl {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-tbl td {
            padding: 4px 8px;
            vertical-align: top;
            font-size: 8pt;
            color: #475569;
            border: none;
            line-height: 1.45;
        }
        .footer-tbl strong {
            color: #1B2A4A;
        }
        .footer-page-meta {
            text-align: center;
            padding: 8px 8px 4px 8px;
            font-size: 7.5pt;
            color: #64748b;
            border: none;
        }

        .auto-msg {
            text-align: center;
            font-size: 9pt;
            color: #94a3b8;
            margin-top: 28px;
            padding-top: 14px;
            border-top: 1px dashed #cbd5e1;
            line-height: 1.55;
        }

        .ltr {
            direction: ltr;
            text-align: left;
        }

        .pdf-logo-wordmark {
            display: inline-block;
            text-align: center;
            padding: 4px 8px 0 8px;
        }
        .pdf-logo-wordmark-ar {
            font-size: 17pt;
            font-weight: bold;
            color: #1B2A4A;
            margin: 0 0 2px 0;
        }
        .pdf-logo-wordmark-en {
            font-size: 10pt;
            color: #64748b;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            margin: 0;
        }
        .pdf-logo-wordmark-line {
            height: 3px;
            width: 120px;
            background-color: #c4a035;
            margin: 10px auto 0 auto;
        }

        @yield('extra-styles')
    </style>
</head>
<body>
    <htmlpagefooter name="rakez_pdf_contract_footer">
        <div class="footer-wrap">
            <table class="footer-tbl">
                <tr>
                    <td style="text-align: right; width: 32%;"><strong>شركة راكز العقارية</strong><br/>RAKEZ REAL ESTATE CO.</td>
                    <td style="text-align: center; width: 36%;">سجل تجاري: 1010691801<br/>المملكة العربية السعودية — الرياض، شارع أنس بن مالك، حي الملقا</td>
                    <td style="text-align: left; width: 32%; direction: ltr;">920015711<br/>www.rakez.sa</td>
                </tr>
                <tr>
                    <td colspan="3" class="footer-page-meta">
                        <span dir="ltr">— {PAGENO} / {nbpg} —</span>
                    </td>
                </tr>
            </table>
        </div>
    </htmlpagefooter>
    <sethtmlpagefooter name="rakez_pdf_contract_footer" value="on" />

    @php
        $rakezLogoSrcForMpdf = null;
        foreach (['images/rakez-logo.png', 'images/rakez-logo.jpg', 'images/logo.png', 'images/logo.jpg'] as $rel) {
            $full = public_path($rel);
            if (! is_string($full) || ! is_readable($full)) {
                continue;
            }
            $resolved = realpath($full);
            if ($resolved === false) {
                continue;
            }
            $normalized = str_replace('\\', '/', $resolved);
            if ($normalized === '') {
                continue;
            }
            $rakezLogoSrcForMpdf = strncmp($normalized, '/', 1) === 0
                ? 'file://' . $normalized
                : 'file:///' . $normalized;
            break;
        }
    @endphp

    <table class="pdf-letterhead" cellpadding="0" cellspacing="0">
        <tr>
            <td class="pdf-letterhead-bar">&nbsp;</td>
        </tr>
        <tr>
            <td class="pdf-letterhead-body">
                @if($rakezLogoSrcForMpdf)
                    <img src="{{ $rakezLogoSrcForMpdf }}" alt="راكز" style="max-height: 64px; width: auto; vertical-align: middle;">
                @else
                    <div class="pdf-logo-wordmark">
                        <div class="pdf-logo-wordmark-ar">شركة راكز العقارية</div>
                        <div class="pdf-logo-wordmark-en">Rakez Real Estate</div>
                        <div class="pdf-logo-wordmark-line"></div>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    @yield('content')
</body>
</html>
