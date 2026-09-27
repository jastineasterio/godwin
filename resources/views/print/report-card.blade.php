<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Progress Report — {{ $student->full_name }}</title>
    <style>
        /* ---- Print-optimised report card (A4) ---------------------- */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Arial, sans-serif; color: #1E293B; background: #F1F5F9; padding: 24px; }
        .sheet { max-width: 800px; margin: 0 auto; background: #fff; padding: 36px; box-shadow: 0 10px 30px rgba(30,41,59,.15); }
        .toolbar { max-width: 800px; margin: 0 auto 16px; display: flex; gap: 10px; justify-content: flex-end; }
        .toolbar button { background:#D81B60; color:#fff; border:0; padding:10px 20px; border-radius:8px; font-weight:700; cursor:pointer; }
        .toolbar a { background:#0288D1; color:#fff; padding:10px 20px; border-radius:8px; font-weight:700; text-decoration:none; }
        header { display:flex; align-items:center; gap:16px; border-bottom:3px solid #D81B60; padding-bottom:16px; }
        .logo { width:56px; height:56px; border-radius:14px; background:#D81B60; color:#fff; display:grid; place-items:center; font-size:24px; font-weight:800; }
        h1 { font-size:20px; color:#D81B60; }
        .tagline { font-size:12px; color:#64748B; }
        .motto { font-size:11px; color:#0288D1; font-weight:700; margin-top:2px; }
        .meta { display:flex; justify-content:space-between; font-size:12px; color:#475569; margin-top:10px; flex-wrap:wrap; gap:8px; }
        h2 { font-size:13px; text-transform:uppercase; letter-spacing:.08em; color:#D81B60; margin:22px 0 8px; border-bottom:1px solid #E2E8F0; padding-bottom:4px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:8px 10px; border:1px solid #E2E8F0; text-align:left; }
        th { background:#F8F9FA; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#475569; }
        td.num { text-align:center; font-weight:700; }
        .grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
        .kpi { border:1px solid #E2E8F0; border-radius:10px; padding:10px; text-align:center; }
        .kpi .v { font-size:20px; font-weight:800; color:#D81B60; }
        .kpi .l { font-size:10px; text-transform:uppercase; color:#64748B; letter-spacing:.05em; }
        .remark { font-size:12px; padding:8px 0; border-bottom:1px dashed #E2E8F0; }
        .sign { display:flex; justify-content:space-between; margin-top:40px; gap:20px; }
        .sign div { width:45%; border-top:1px solid #94A3B8; padding-top:6px; font-size:11px; color:#64748B; text-align:center; }
        footer { margin-top:24px; border-top:1px solid #E2E8F0; padding-top:10px; font-size:10px; color:#94A3B8; text-align:center; }
        .grade { display:inline-block; background:#FBC02D; color:#1E293B; font-weight:800; padding:4px 10px; border-radius:999px; font-size:12px; }
        @media print {
            body { background:#fff; padding:0; }
            .toolbar { display:none; }
            .sheet { box-shadow:none; max-width:none; padding:12mm; }
            table { page-break-inside:avoid; }
        }
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
                <div class="tagline">{{ $school['location']['full'] }}</div>
                <div class="motto">{{ $school['motto'] }}</div>
            </div>
            <div style="margin-left:auto;text-align:right">
                <div class="tagline">Tel: {{ implode(' | ', $school['phones']) }}</div>
                <div class="tagline">Student Progress Report</div>
            </div>
        </header>

        <div class="meta">
            <span><strong>Student:</strong> {{ $student->full_name }}</span>
            <span><strong>Reg No:</strong> {{ $student->reg_no }}</span>
            <span><strong>Class:</strong> {{ $class?->name ?? '—' }}</span>
            <span><strong>Term:</strong> {{ $term?->name ?? '—' }}</span>
        </div>

        <h2>Attendance Summary</h2>
        <div class="grid">
            <div class="kpi"><div class="v">{{ $attendance['rate'] ?? 0 }}%</div><div class="l">Attendance Rate</div></div>
            <div class="kpi"><div class="v">{{ $attendance['present'] }}</div><div class="l">Present</div></div>
            <div class="kpi"><div class="v">{{ $attendance['absent'] }}</div><div class="l">Absent</div></div>
            <div class="kpi"><div class="v">{{ $attendance['late'] }}</div><div class="l">Late</div></div>
        </div>

        <h2>Subject Performance</h2>
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Assessments</th>
                    <th>Score (%)</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subjects as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="num">{{ $row['entries'] }}</td>
                        <td class="num">{{ $row['percentage'] }}%</td>
                        <td>{{ $row['percentage'] >= 75 ? 'Excellent' : ($row['percentage'] >= 50 ? 'Good' : 'Needs support') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:#94A3B8">No assessments recorded this term.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Overall Performance</h2>
        <div class="grid">
            <div class="kpi"><div class="v">{{ $overall }}%</div><div class="l">Overall Average</div></div>
            <div class="kpi"><div class="v">{{ $position ?? '—' }}{{ $position ? ' / '.$classSize : '' }}</div><div class="l">Position in Class</div></div>
            <div class="kpi" style="grid-column:span 2"><span class="grade">{{ $grade }}</span></div>
        </div>

        <h2>Character &amp; Behaviour Remarks</h2>
        @forelse ($remarks as $remark)
            <div class="remark">
                <strong>{{ $remark->title }}:</strong> {{ $remark->note }}
                <span style="color:#94A3B8">({{ $remark->occurred_on?->format('d M Y') }})</span>
            </div>
        @empty
            <div class="remark" style="color:#94A3B8">No remarks recorded this term.</div>
        @endforelse

        <div class="sign">
            <div>Class Teacher's Signature</div>
            <div>Parent / Guardian Signature</div>
        </div>

        <footer>
            {{ $school['name'] }} · {{ $school['slogan'] }} · Generated {{ now()->format('d M Y') }}
        </footer>
</body>
</html>