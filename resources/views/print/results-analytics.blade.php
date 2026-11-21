<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>School-Wide Results & Analytics — {{ $school['name'] }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Arial, sans-serif; color: #1E293B; background: #F1F5F9; padding: 24px; }
        .sheet { max-width: 900px; margin: 0 auto; background: #fff; padding: 36px; box-shadow: 0 10px 30px rgba(30,41,59,.15); }
        .toolbar { max-width: 900px; margin: 0 auto 16px; display: flex; gap: 10px; justify-content: flex-end; }
        .toolbar button { background:#0288D1; color:#fff; border:0; padding:10px 20px; border-radius:8px; font-weight:700; cursor:pointer; }
        .toolbar a { background:#64748B; color:#fff; padding:10px 20px; border-radius:8px; font-weight:700; text-decoration:none; }
        header { display:flex; align-items:center; gap:16px; border-bottom:3px solid #0288D1; padding-bottom:16px; margin-bottom:20px; }
        .logo { width:56px; height:56px; border-radius:14px; background:#0288D1; color:#fff; display:grid; place-items:center; font-size:24px; font-weight:800; }
        h1 { font-size:20px; color:#0288D1; }
        .tagline { font-size:12px; color:#64748B; }
        .motto { font-size:11px; color:#0288D1; font-weight:700; margin-top:2px; }
        .meta { display:flex; justify-content:space-between; font-size:12px; color:#475569; margin-top:10px; flex-wrap:wrap; gap:8px; }
        .badge-readonly { display:inline-flex; align-items:center; gap:6px; background:#FDECEA; border:1px solid #FCA5A5; color:#B91C1C; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; padding:4px 10px; border-radius:999px; margin-left:auto; }
        .notice { font-size:12px; color:#64748B; margin-top:6px; max-width:40%; margin-left:auto; text-align:right; line-height:1.4; }
        h2 { font-size:13px; text-transform:uppercase; letter-spacing:.08em; color:#0288D1; margin:22px 0 8px; border-bottom:1px solid #E2E8F0; padding-bottom:4px; }
        table { width:100%; border-collapse:collapse; font-size:12px; margin-top:8px; }
        th, td { padding:7px 10px; border:1px solid #E2E8F0; text-align:left; }
        th { background:#F8F9FA; font-size:10px; text-transform:uppercase; letter-spacing:.05em; color:#475569; }
        td.num { text-align:center; font-weight:700; }
        .grid { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-top:10px; }
        .kpi { border:1px solid #E2E8F0; border-radius:10px; padding:10px; text-align:center; background:#F8F9FA; }
        .kpi .v { font-size:19px; font-weight:800; color:#0288D1; }
        .kpi .l { font-size:10px; text-transform:uppercase; color:#64748B; letter-spacing:.05em; }
        .grade { display:inline-block; background:#FBC02D; color:#1E293B; font-weight:800; padding:4px 10px; border-radius:999px; font-size:12px; }
        @media print {
            body { background:#fff; padding:0; }
            .toolbar { display:none; }
            .sheet { box-shadow:none; max-width:none; padding:12mm; }
            table { page-break-inside:avoid; }
            .notice { max-width:none; width:auto; margin:6px 0 0 0; text-align:left; float:none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ url()->previous() ?? url('/') }}">← Back</a>
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
                <span class="badge-readonly">Read-Only Report</span>
                <div class="notice">
                    Marks entry, submission, approval and return are handled through the dedicated marks workflow.<br>
                    No edits can be made from this report.
                </div>
            </div>
        </header>

        <div class="meta">
            <span><strong>Report:</strong> School-Wide Results &amp; Analytics</div>
            <span><strong>Generated:</strong> {{ $generated_at }}</span>
            <span><strong>Prepared by:</strong> {{ $user_role_label }} ({{ $user_role }})</span>
            <span><strong>Academic Year:</strong> {{ $selected['academic_year_id'] ? (App\Models\AcademicYear::find($selected['academic_year_id'])->name ?? '—') : 'All years' }}</span>
            <span><strong>Term:</strong> {{ $selected['term_id'] ? (App\Models\Term::find($selected['term_id'])->name ?? '—') : 'All terms' }}</span>
            <span><strong>Examination:</strong> {{ $selected['examination_id'] ? (App\Models\Examination::find($selected['examination_id'])->name ?? '—') : 'All examinations' }}</span>
            <span><strong>Class:</strong> {{ $selected['class_id'] ? (App\Models\SchoolClass::find($selected['class_id'])->name ?? '—') : 'All classes' }}</span>
            <span><strong>Subject:</strong> {{ $selected['subject_id'] ? (App\Models\Subject::find($selected['subject_id'])->name ?? '—') : 'All subjects' }}</span>
        </div>

        <h2>Overview</h2>
        <div class="grid">
            <div class="kpi"><div class="v">{{ $overview['total_examinations'] }}</div><div class="l">Total Examinations</div></div>
            <div class="kpi"><div class="v">{{ $overview['results_recorded'] }}</div><div class="l">Results Recorded</div></div>
            <div class="kpi"><div class="v">{{ $overview['pending_submissions'] }}</div><div class="l">Pending Submissions</div></div>
            <div class="kpi"><div class="v">{{ $overview['submitted_awaiting_approval'] }}</div><div class="l">Submitted Awaiting Approval</div></div>
            <div class="kpi"><div class="v">{{ $overview['approved_results'] }}</div><div class="l">Approved Results</div></div>
        </div>
        <div class="grid" style="margin-top:10px;">
            <div class="kpi"><div class="v">{{ $overview['class_average'] }}%</div><div class="l">Overall School Average</div></div>
        </div>

        <h2>Class Performance</h2>
        <table>
            <thead>
                <tr>
                    <th>Class</th><th class="num">Average (%)</th><th class="num">Highest (%)</th><th class="num">Lowest (%)</th><th class="num">Results</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($classAnalytics as $c)
                    <tr>
                        <td>{{ $c['name'] }}</td>
                        <td class="num">{{ $c['average'] }}%</td>
                        <td class="num">{{ $c['highest'] }}%</td>
                        <td class="num">{{ $c['lowest'] }}%</td>
                        <td class="num">{{ $c['results_recorded'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:#94A3B8">No class results recorded.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Subject Performance</h2>
        <table>
            <thead>
                <tr>
                    <th>Subject</th><th class="num">Average (%)</th><th class="num">Highest (%)</th><th class="num">Lowest (%)</th><th class="num">Students</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subjectAnalytics as $s)
                    <tr>
                        <td>{{ $s['name'] }}</td>
                        <td class="num">{{ $s['average'] }}%</td>
                        <td class="num">{{ $s['highest'] }}%</td>
                        <td class="num">{{ $s['lowest'] }}%</td>
                        <td class="num">{{ $s['results_count'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;color:#94A3B8">No subject results recorded.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Grade Distribution</h2>
        <table>
            <thead>
                <tr>
                    <th>Grade</th><th class="num">Students</th><th class="num">Percentage</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($grade_distribution as $g)
                    <tr>
                        <td><span class="grade">{{ $g['label'] }}</span></td>
                        <td class="num">{{ $g['count'] }}</td>
                        <td class="num">{{ $g['percentage'] }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;color:#94A3B8">No graded results recorded.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Individual Student Performance</h2>
        <table>
            <thead>
                <tr>
                    <th>Student</th><th>Admission No.</th><th>Class</th><th>Subject</th><th>Examination</th>
                    <th class="num">Marks</th><th class="num">Max</th><th>Grade</th><th class="num">%</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($studentPerformance as $s)
                    <tr>
                        <td>{{ $s['student_name'] }}</td>
                        <td class="num">{{ $s['reg_no'] }}</td>
                        <td>{{ $s['class'] }}</td>
                        <td>{{ $s['subject'] }}</td>
                        <td>{{ $s['examination'] }}</td>
                        <td class="num">{{ $s['marks_obtained'] }}</td>
                        <td class="num">{{ $s['max_marks'] }}</td>
                        <td><span class="grade">{{ $s['grade'] }}</span></td>
                        <td class="num">{{ $s['percentage'] ?? '—' }}%</td>
                        <td class="num">{{ ucfirst($s['status'] ?? 'draft') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="text-align:center;color:#94A3B8">No student results recorded.</td></tr>
                @endforelse
            </tbody>
        </table>

        <footer>
            {{ $school['name'] }} · {{ $school['slogan'] }} · Generated {{ $generated_at }}
        </footer>
    </div>
</body>
</html>
