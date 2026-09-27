<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->receipt_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Arial, sans-serif; background:#F1F5F9; color:#1E293B; padding:24px; }
        .receipt { max-width: 460px; margin: 0 auto; background:#fff; padding:32px; box-shadow:0 10px 30px rgba(30,41,59,.15); }
        .toolbar { max-width:460px; margin:0 auto 16px; display:flex; gap:10px; justify-content:flex-end; }
        .toolbar button { background:#D81B60; color:#fff; border:0; padding:10px 20px; border-radius:8px; font-weight:700; cursor:pointer; }
        .toolbar a { background:#0288D1; color:#fff; padding:10px 20px; border-radius:8px; font-weight:700; text-decoration:none; }
        .center { text-align:center; }
        .logo { width:52px; height:52px; border-radius:14px; background:#D81B60; color:#fff; display:grid; place-items:center; font-size:22px; font-weight:800; margin:0 auto 10px; }
        h1 { font-size:17px; color:#D81B60; }
        .muted { font-size:11px; color:#64748B; }
        .rule { border-top:2px dashed #CBD5E1; margin:16px 0; }
        table { width:100%; font-size:12px; border-collapse:collapse; }
        td { padding:5px 0; }
        td:last-child { text-align:right; font-weight:700; }
        .amount { background:#0288D1; color:#fff; border-radius:10px; padding:14px; text-align:center; margin:14px 0; }
        .amount .v { font-size:26px; font-weight:800; }
        .amount .l { font-size:10px; text-transform:uppercase; letter-spacing:.1em; opacity:.85; }
        .thanks { text-align:center; font-size:12px; color:#0288D1; font-weight:700; margin-top:10px; }
        footer { margin-top:16px; border-top:1px solid #E2E8F0; padding-top:8px; text-align:center; font-size:10px; color:#94A3B8; }
        @media print { body { background:#fff; padding:0; } .toolbar { display:none; } .receipt { box-shadow:none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ url()->previous() }}">← Back</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="receipt">
        <div class="center">
            <div class="logo">GW</div>
            <h1>{{ $school['name'] }}</h1>
            <div class="muted">{{ $school['location']['display'] }}</div>
            <div class="muted">Tel: {{ implode(' | ', $school['phones']) }}</div>
        </div>

        <div class="rule"></div>

        <table>
            <tr><td>Receipt No.</td><td>{{ $payment->receipt_number }}</td></tr>
            <tr><td>Date</td><td>{{ $payment->paid_at?->format('d M Y, H:i') }}</td></tr>
            <tr><td>Student</td><td>{{ $student?->full_name }}</td></tr>
            <tr><td>Reg No.</td><td>{{ $student?->reg_no }}</td></tr>
            <tr><td>Invoice</td><td>{{ $invoice?->invoice_number ?? '—' }}</td></tr>
            <tr><td>Method</td><td>{{ $payment->method->label() }}</td></tr>
            @if ($payment->reference)
                <tr><td>Reference</td><td>{{ $payment->reference }}</td></tr>
            @endif
            <tr><td>Received by</td><td>{{ $receivedBy?->name ?? 'Office' }}</td></tr>
        </table>

        <div class="amount">
            <div class="l">Amount Received (TZS)</div>
            <div class="v">{{ number_format((float) $payment->amount, 0) }}</div>
        </div>

        @if ($invoice)
            <table>
                <tr><td>Invoice total</td><td>{{ number_format((float) $invoice->total_amount, 0) }}</td></tr>
                <tr><td>Total paid to date</td><td>{{ number_format((float) $invoice->amount_paid, 0) }}</td></tr>
                <tr><td>Balance remaining</td><td>{{ number_format((float) $invoice->balance, 0) }}</td></tr>
            </table>
        @endif

        <div class="thanks">Thank you — Asanteni sana! 🌟</div>
        <div class="muted center" style="margin-top:4px">{{ $school['motto'] }}</div>

        <footer>Printed {{ now()->format('d M Y, H:i') }} · {{ $school['name'] }}</footer>
    </div>
</body>
</html>