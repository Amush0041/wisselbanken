<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Estimate {{ $quote->quote_number }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            margin: 0;
            line-height: 1.4;
        }
        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .header td { vertical-align: top; padding: 0; }
        .brand img { max-width: 200px; max-height: 72px; }
        .brand-name { font-size: 18px; font-weight: 700; color: #4a171e; letter-spacing: 0.02em; }
        .doc-title {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #4a171e;
            margin: 0 0 4px;
            text-align: right;
        }
        .meta {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        .meta th,
        .meta td {
            border: 1px solid #d8cfc7;
            padding: 5px 8px;
        }
        .meta th {
            background: #f6f2ec;
            text-align: left;
            font-weight: 600;
            color: #4a171e;
            width: 42%;
        }
        .meta td { text-align: right; font-weight: 500; }

        .parties {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .parties td {
            vertical-align: top;
            width: 50%;
            padding: 0 10px 0 0;
        }
        .parties td:last-child { padding-right: 0; padding-left: 10px; }
        .block-label {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6f5a5d;
            margin-bottom: 4px;
        }
        .block-name {
            font-size: 12px;
            font-weight: 700;
            color: #4a171e;
            margin-bottom: 3px;
        }
        .block-text {
            font-size: 9px;
            color: #333;
            white-space: pre-line;
        }
        .project-sub {
            font-size: 9px;
            color: #555;
            margin-top: 2px;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .items th,
        .items td {
            border: 1px solid #d8cfc7;
            padding: 6px 7px;
            vertical-align: top;
        }
        .items th {
            background: #4a171e;
            color: #fff;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .items tbody tr:nth-child(even) td { background: #faf8f6; }
        .c-center { text-align: center; }
        .c-right { text-align: right; white-space: nowrap; }
        .item-desc { font-weight: 600; }
        .item-notes { font-size: 8px; color: #666; margin-top: 2px; }
        .type-pill {
            display: inline-block;
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 1px 4px;
            border-radius: 3px;
            background: #efe8e0;
            color: #4a171e;
        }

        .totals-wrap { width: 100%; margin-top: 4px; }
        .totals {
            width: 46%;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 9px;
        }
        .totals td {
            padding: 5px 8px;
            border: 1px solid #d8cfc7;
        }
        .totals .label { text-align: left; color: #555; }
        .totals .value { text-align: right; font-weight: 600; }
        .totals tr.grand td {
            background: #4a171e;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            border-color: #4a171e;
        }

        .footer-block {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid #e8ddd4;
            page-break-inside: avoid;
        }
        .footer-block h4 {
            margin: 0 0 4px;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #4a171e;
        }
        .footer-block p {
            margin: 0;
            font-size: 9px;
            color: #333;
            white-space: pre-line;
        }
        .empty-note {
            padding: 12px;
            border: 1px dashed #d8cfc7;
            text-align: center;
            color: #666;
            font-size: 9px;
        }
        .powered-by {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #e8ddd4;
            text-align: center;
            page-break-inside: avoid;
        }
        .powered-by-inner {
            display: inline-block;
        }
        .powered-by-text {
            font-size: 8px;
            color: #9e8c8f;
            letter-spacing: 0.03em;
        }
        .powered-by-logo {
            vertical-align: middle;
            max-height: 14px;
            max-width: 90px;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 48%;">
                <div class="brand">
                    @if($companyLogoPath)
                        <img src="{{ $companyLogoPath }}" alt="{{ $companyName ?: 'Company Logo' }}">
                    @elseif($companyName !== '')
                        <div class="brand-name">{{ $companyName }}</div>
                    @elseif($platformLogoPath)
                        <img src="{{ $platformLogoPath }}" alt="Wisselbanken">
                    @endif
                </div>
            </td>
            <td style="width: 52%;">
                <div class="doc-title">ESTIMATE</div>
                <table class="meta">
                    <tr>
                        <th>Quote #</th>
                        <td>{{ $quote->quote_number }}</td>
                    </tr>
                    @if($projectName !== '')
                    <tr>
                        <th>Project name or number</th>
                        <td>{{ $projectName }}</td>
                    </tr>
                    @endif
                    <tr>
                        <th>Date</th>
                        <td>{{ $displayDate }}</td>
                    </tr>
                    @if($shippingMethod !== '')
                    <tr>
                        <th>Shipping</th>
                        <td>{{ $shippingMethod }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="block-label">Deliver to</div>
                <div class="block-name">{{ $customerName }}</div>
                @if($customerAddress !== '')
                    <div class="block-text">{{ $customerAddress }}</div>
                @endif
                @if($customerEmail !== '' || $customerPhone !== '')
                    <div class="block-text" style="margin-top:4px;">
                        @if($customerEmail !== '')
                            {{ $customerEmail }}
                        @endif
                        @if($customerEmail !== '' && $customerPhone !== '')
                            <br>
                        @endif
                        @if($customerPhone !== '')
                            {{ $customerPhone }}
                        @endif
                    </div>
                @endif
            </td>
            <td>
                @if($projectSubtitle !== '' && $projectSubtitle !== $projectName)
                    <div class="block-label">Project details</div>
                    <div class="block-name">{{ $projectSubtitle }}</div>
                    @if($projectAddress !== '')
                        <div class="block-text" style="margin-top:3px;">{{ $projectAddress }}</div>
                    @endif
                @elseif($projectAddress !== '')
                    <div class="block-label">Project details</div>
                    <div class="block-text">{{ $projectAddress }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if(count($lineItems) === 0)
        <div class="empty-note">No line items on this estimate.</div>
    @else
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 6%;" class="c-center">#</th>
                    <th style="width: 54%;">Description</th>
                    <th style="width: 10%;" class="c-center">Qty</th>
                    <th style="width: 15%;" class="c-right">Unit price</th>
                    <th style="width: 15%;" class="c-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lineItems as $row)
                    <tr>
                        <td class="c-center">{{ $row['index'] }}</td>
                        <td>
                            <div class="item-desc">{{ $row['description'] }}</div>
                            @if($row['notes'] !== '')
                                <div class="item-notes">{{ $row['notes'] }}</div>
                            @endif
                        </td>
                        <td class="c-center">{{ number_format($row['quantity']) }}</td>
                        <td class="c-right">{{ $formatMoney($row['unit_price']) }}</td>
                        <td class="c-right">{{ $formatMoney($row['subtotal']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="totals-wrap">
        <table class="totals">
            <tr>
                <td class="label">Subtotal</td>
                <td class="value">{{ $formatMoney($lineSubtotal) }}</td>
            </tr>
            @if($hasDiscount)
            <tr>
                <td class="label">
                    Discount
                    @if($discountRaw !== '')
                        ({{ $discountRaw }})
                    @endif
                </td>
                <td class="value">-{{ $formatMoney($discountAmount) }}</td>
            </tr>
            @endif
            @if($hasShipping)
            <tr>
                <td class="label">Shipping</td>
                <td class="value">{{ $formatMoney($shipping) }}</td>
            </tr>
            @endif
            <tr class="grand">
                <td class="label">Total</td>
                <td class="value">{{ $formatMoney($grandTotal) }}</td>
            </tr>
        </table>
    </div>

    @if($terms !== '')
        <div class="footer-block">
            <h4>Terms &amp; conditions</h4>
            <p>{{ $terms }}</p>
        </div>
    @endif

    @if($notes !== '')
        <div class="footer-block">
            <h4>Notes</h4>
            <p>{{ $notes }}</p>
        </div>
    @endif

    <div class="powered-by">
        <span class="powered-by-text">
            Powered by&nbsp;
            @if($platformLogoPath)
                <img src="{{ $platformLogoPath }}" class="powered-by-logo" alt="Wisselbanken">&nbsp;&#8482;
            @else
                Wisselbanken&#8482;
            @endif
            &nbsp;&mdash;&nbsp;Intelligent Procurement for Modern Construction
        </span>
    </div>
</body>
</html>
