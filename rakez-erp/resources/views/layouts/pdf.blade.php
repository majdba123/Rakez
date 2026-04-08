<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@yield('title')</title>
    <style>
        @page {
            margin: 22mm 14mm 36mm 14mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'dejavusans', 'dejavu sans', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 10.5pt;
            color: #334155;
            margin: 0;
            padding: 0;
            line-height: 1.55;
        }

        /* ----- Letterhead ----- */
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

        /* ----- Document titles ----- */
        .doc-title-wrap {
            margin: 0 0 18px 0;
            padding: 0 0 14px 0;
            border-bottom: 1px solid #cbd5e1;
        }
        .doc-title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            color: #1B2A4A;
            margin: 0 0 6px 0;
            letter-spacing: 0.02em;
        }
        .doc-title-en {
            text-align: center;
            font-size: 9.5pt;
            color: #64748b;
            margin: 0 0 10px 0;
            font-weight: normal;
        }
        .doc-subtitle {
            text-align: center;
            font-size: 9pt;
            color: #64748b;
            margin: 0;
            padding: 8px 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        /* ----- Section ----- */
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #fff;
            background-color: #1B2A4A;
            margin: 22px 0 0 0;
            margin-bottom: 0;
            padding: 8px 12px;
            border: 1px solid #142038;
        }
        .section-title.first {
            margin-top: 6px;
        }

        /* ----- Key / value tables ----- */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 16px 0;
            border: 1px solid #cbd5e1;
            border-top: none;
        }
        .info-table td {
            padding: 9px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9.5pt;
            vertical-align: top;
        }
        .info-table tr:last-child td {
            border-bottom: none;
        }
        .info-table td:first-child {
            width: 36%;
            background-color: #f1f5f9;
            font-weight: bold;
            color: #1e293b;
            border-left: 1px solid #e2e8f0;
        }
        .info-table td:last-child {
            background-color: #ffffff;
            color: #334155;
        }

        /* ----- Grid data tables ----- */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            border: 1px solid #cbd5e1;
        }
        .data-table th {
            background-color: #1B2A4A;
            color: #ffffff;
            padding: 9px 8px;
            border: 1px solid #142038;
            font-size: 9pt;
            text-align: center;
            font-weight: bold;
        }
        .data-table td {
            padding: 8px 8px;
            border: 1px solid #e2e8f0;
            text-align: center;
            font-size: 9pt;
            background-color: #ffffff;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* ----- Summary ----- */
        .summary-box {
            margin: 14px 0;
            padding: 14px 16px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
        }
        .summary-box p {
            margin: 5px 0;
            font-size: 9.5pt;
        }

        .empty-msg {
            color: #94a3b8;
            font-style: italic;
            padding: 12px;
            font-size: 9.5pt;
        }

        /* ----- Status ----- */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            font-size: 8.5pt;
            font-weight: bold;
        }
        .status-pending { background-color: #fde68a; color: #78350f; }
        .status-approved { background-color: #bbf7d0; color: #14532d; }
        .status-paid { background-color: #bfdbfe; color: #1e3a5f; }
        .status-rejected { background-color: #fecaca; color: #7f1d1d; }
        .status-received { background-color: #bbf7d0; color: #14532d; }
        .status-confirmed { background-color: #bfdbfe; color: #1e3a5f; }
        .status-refunded { background-color: #fecaca; color: #7f1d1d; }
        .status-draft { background-color: #fde68a; color: #78350f; }
        .status-active { background-color: #bbf7d0; color: #14532d; }
        .status-expired { background-color: #e2e8f0; color: #475569; }
        .status-terminated { background-color: #fecaca; color: #7f1d1d; }

        /* ----- Footer (htmlpagefooter) ----- */
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

        .auto-msg {
            text-align: center;
            font-size: 8.5pt;
            color: #94a3b8;
            margin-top: 28px;
            padding-top: 12px;
            border-top: 1px dashed #cbd5e1;
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
    <htmlpagefooter name="rakez_pdf_footer">
        <div class="footer-wrap">
            <table class="footer-tbl">
                <tr>
                    <td style="text-align: right; width: 32%;"><strong>شركة راكز العقارية</strong><br/>RAKEZ REAL ESTATE CO.</td>
                    <td style="text-align: center; width: 36%;">سجل تجاري: 1010691801<br/>المملكة العربية السعودية — الرياض، شارع أنس بن مالك، حي الملقا</td>
                    <td style="text-align: left; width: 32%; direction: ltr;">920015711<br/>www.rakez.sa</td>
                </tr>
            </table>
        </div>
    </htmlpagefooter>
    <sethtmlpagefooter name="rakez_pdf_footer" value="on" />

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
