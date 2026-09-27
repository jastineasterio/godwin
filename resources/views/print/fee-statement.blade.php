<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fee Statement — {{ $student->full_name }}</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:"Segoe UI", Arial, sans-serif; background:#F1F5F9; color:#1E293B; padding:24px; }
        .sheet { max-width:860px; margin:0 auto; background:#fff; padding:36px; box-shadow:0 10px 30px rgba(30,41,59,.15); }
        .toolbar { max-width:860px; margin:0 auto 16px; display:flex; gap:10px; justify-content:flex-end; }
        .toolbar button { background:#D81B60; color:#fff; border:0; padding:10px 20px; border-radius:8px; font-weight:700; cursor:pointer; }
        .toolbar a { background:#0288D1; color:#fff; padding:10px 20px; border-radius:8px; font-weight:700; text-decoration:none; }
        header { display:flex; align-items:center; gap:16px; border-bottom:3px solid #0288D1; padding-bottom:16px; }
        .logo { width:56px; height:56px; border-radius:14px; background:#0288D1; color:#fff; display:grid; place-items:center; font-size:24px; font-weight:800; }
        h1 { font-size:20px; color:#0288D1; }
        .muted { font-size:12px; color:#64748B; }
        .motto { font-size:11px; color:#D81B60; font-weight:700; margin-top:2px; }
        .meta { display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; font-size:12px; color:#475569; margin-top:10px; }
        h2 { font-size:13px; text-transform:uppercase; letter-spacing:.08em; color:#0288D1; margin:22px 0 8px; border-bottom:1px solid #E2E8F0; padding-bottom:4px; }
        table { width:100%; border-collapse:collapse; font-size:12px; }
        th, td { padding:7px 9px; border:1px solid #E2E8F0; text-align:left; }
        th { background:#F8F9FA; font-size:10px; text-transform:uppercase; letter-spacing:.05em; color:#475569; }
        td.num { text-align:right; font-weight:700; }
        .grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
        .kpi { border:1px solid #E2E8F0; border-radius:10px; padding:12px; text-align:center; }
        .kpi .v { font-size:20px; font-weight:800; color:#0288D1; }
        .kpi.due .v { color:#D81B60; }
        .kpi .l { font-size:10px; text-transform:uppercase; color:#64748B; letter-spacing:.05em; }
        .pill { display:inline-block; font-size:10px; font-weight:700; padding:2px 8px; border-radius:999px; background:#E2E8F0; color:#475569; }
        .pill.paid { background:#DCFCE7; color:#15803D; }
        .pill.partial, .pill.overdue { background:#FEF3C7; color:#B45309; }
        .pill.unpaid { background:#FFE4E6; color:#BE123C; }
        footer { margin-top:24px; border-top:1px solid #E2E8F0; padding-top:10px; font-size:10px; color:#94A3B8; text-align:center; }
        @media print { body { background:#fff; padding:0; } .toolbar { display:none; } .sheet { box-shadow:none; } table { page-break-inside:avoid; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ url()->previous() }}">← Back</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="sheet">
        <header>
            <div class="logo">GW</div>
            <div>
                <h1>{{ $school['name'] }}</h1>
                <div class="muted">{{ $school['location']['full'] }}</div>
                <div class="motto">{{ $school['motto'] }}</div>
            </div>
            <div style="margin-left:auto;text-align:right">
                <div class="muted">Tel: {{ implode(' | ', $school['phones']) }}</div>
                <div class="muted">Fee Statement</div>
            </div>
        </header>

        <div class="meta">
            <span><strong>Student:</strong> {{ $student->full_name }}</span>
            <span><strong>Reg No:</strong> {{ $student->reg_no }}</span>
            <span><strong>Class:</strong> {{ $class?->name ?? '—' }}</span>
            <span><strong>Date:</strong> {{ now()->format('d M Y') }}</span>
        </div>

        <h2>Account Summary</h2>
        <div class="grid">
            <div class="kpi"><div class="v">{{ number_format($totals['billed'], 0) }}</div><div class="l">Total Billed (TZS)</div></div>
            <div class="kpi"><div class="v">{{ number_format($totals['paid'], 0) }}</div><div class="l">Total Paid (TZS)</div></div>
            <div class="kpi due"><div class="v">{{ number_format($totals['balance'], 0) }}</div><div class="l">Balance Due (TZS)</div></div>
        </div>

        <h2>Invoices</h2>
        <table>
            <thead>
                <tr>
                    <th>Invoice</th><th>Issued</th><th>Due</th><th>Details</th>
                    <th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice['number'] }}</td>
                        <td>{{ $invoice['issue_date'] }}</td>
                        <td>{{ $invoice['due_date'] }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($invoice['items'], 40) }}</td>
                        <td class="num">{{ number_format($invoice['total'], 0) }}</td>
                        <td class="num">{{ number_format($invoice['paid'], 0) }}</td>
                        <td class="num">{{ number_format($invoice['balance'], 0) }}</td>
                        <td><span class="pill {{ strtolower($invoice['status']) }}">{{ $invoice['status'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;color:#94A3B8">No invoices issued.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Payment History</h2>
        <table>
            <thead>
                <tr><th>Receipt</th><th>Date</th><th>Method</th><th>Invoice</th><th class="num">Amount (TZS)</th></tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td>{{ $payment['receipt'] }}</td>
                        <td>{{ $payment['date'] }}</td>
                        <td>{{ $payment['method'] }}</td>
                        <td>{{ $payment['invoice'] ?? '—' }}</td>
                        <td class="num">{{ number_format($payment['amount'], 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:#94A3B8">No payments received yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        <footer>
            {{ $school['name'] }} · {{ $school['slogan'] }} · Generated {{ now()->format('d M Y, H:i') }}
        </footer>
</body>
</html>