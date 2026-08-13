@php
    $earnings = $data['earnings'] ?? [];
    $deductions = $data['deductions'] ?? [];
    $employer = $data['employer_contributions'] ?? [];
    $notes = $data['notes'] ?? [];
    $isRecovery = (float) ($data['net_pay'] ?? 0) < 0;
    $payMonthLabel = $data['pay_month_label'] ?? '';
    $paidDays = $data['paid_days'] ?? 0;
    $daysInMonth = $data['days_in_month'] ?? 0;
@endphp
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <title>Payslip{{ $payMonthLabel !== '' ? ' — '.$payMonthLabel : '' }}</title>
    <style>
        @page { margin: 16mm 14mm; }
        body { font: 10pt/1.4 DejaVu Sans, sans-serif; color: #16181d; }
        .doc__masthead { border-bottom: 2px solid #16181d; padding-bottom: 8pt; margin-bottom: 10pt; }
        .doc__company { font-size: 14pt; font-weight: 700; margin: 0; }
        .doc__meta { color: #5d636e; font-size: 8.5pt; margin: 2pt 0 0; }
        .doc__title { font-size: 11pt; margin: 0 0 10pt; text-transform: uppercase; letter-spacing: .06em; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 3.5pt 4pt; text-align: left; vertical-align: top; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .doc__identity { margin-bottom: 10pt; }
        .doc__identity th { width: 22%; color: #5d636e; font-weight: 600; font-size: 8.5pt; padding-left: 0; }
        .doc__identity td { width: 28%; padding-right: 8pt; }
        .doc__identity tr th, .doc__identity tr td { border-bottom: .5pt solid #d9dde4; }
        .doc__columns { margin: 0 0 8pt; }
        .doc__columns > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }
        .doc__columns > tbody > tr > td.doc__col--earnings { padding-right: 8pt; }
        .doc__columns > tbody > tr > td.doc__col--deductions { padding-left: 8pt; }
        .doc__heads thead th { border-bottom: 1.25pt solid #16181d; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .04em; padding-top: 0; }
        .doc__heads tbody td { border-bottom: .5pt solid #d9dde4; }
        .doc__heads .doc__total td { border-top: 1pt solid #16181d; border-bottom: none; font-weight: 700; }
        .doc__empty { color: #5d636e; font-style: italic; }
        .doc__net { margin: 8pt 0 12pt; border: 1pt solid #16181d; }
        .doc__net td { padding: 6pt 8pt; }
        .doc__net__label { font-weight: 700; text-transform: uppercase; letter-spacing: .04em; font-size: 9pt; }
        .doc__net__amount { font-size: 12pt; font-weight: 700; text-align: right; font-variant-numeric: tabular-nums; }
        .doc__net__words { color: #3d434e; font-size: 9pt; }
        .doc__annexure { margin-top: 12pt; }
        .doc__annexure__heading { font-size: 9pt; text-transform: uppercase; letter-spacing: .04em; margin: 0 0 4pt; }
        .doc__annexure__note { color: #5d636e; font-size: 8pt; margin: 0 0 6pt; }
        .doc__annexure td { border-bottom: .5pt solid #d9dde4; }
        .doc__annexure .doc__total td { border-top: 1pt solid #16181d; border-bottom: none; font-weight: 700; }
        .doc__notes { margin-top: 10pt; font-size: 8.5pt; color: #3d434e; }
        .doc__notes ul { margin: 4pt 0 0; padding-left: 14pt; }
        .doc__working { margin-top: 14pt; font-size: 8.5pt; color: #3d434e; }
        .doc__working td, .doc__working th { border-bottom: .5pt solid #d9dde4; }
        .doc__citations { margin-top: 10pt; font-size: 8pt; color: #5d636e; }
        .doc__footer { margin-top: 16pt; font-size: 8pt; color: #5d636e; border-top: .5pt solid #d9dde4; padding-top: 6pt; }
    </style>
</head>
<body>

<div class="doc__masthead">
    @if (!empty($company['logo']))
        <img src="{{ $company['logo'] }}" alt="" height="40">
    @endif
    <p class="doc__company">{{ $company['name'] ?? '' }}</p>
    <p class="doc__meta">
        {{ $company['address'] ?? '' }}
        @if (!empty($company['gstin'])) · GSTIN {{ $company['gstin'] }} @endif
        @if (!empty($company['pan'])) · PAN {{ $company['pan'] }} @endif
    </p>
</div>

<h1 class="doc__title">Payslip{{ $payMonthLabel !== '' ? ' for '.$payMonthLabel : '' }}</h1>

<table class="doc__identity">
    <tbody>
        <tr>
            <th scope="row">Employee name</th>
            <td>{{ $data['employee_name'] ?? '' }}</td>
            <th scope="row">Employee code</th>
            <td>{{ $data['employee_code'] ?? '' }}</td>
        </tr>
        <tr>
            <th scope="row">Designation</th>
            <td>{{ ($data['designation'] ?? '') !== '' ? $data['designation'] : '—' }}</td>
            <th scope="row">State</th>
            <td>{{ $data['state'] ?? '' }}</td>
        </tr>
        <tr>
            <th scope="row">Wage month</th>
            <td>{{ $payMonthLabel }}</td>
            <th scope="row">Paid days</th>
            <td>{{ $paidDays }} of {{ $daysInMonth }}</td>
        </tr>
        <tr>
            <th scope="row">Days payable</th>
            <td>{{ $data['days_payable'] ?? '' }}</td>
            <th scope="row">Loss of pay</th>
            <td>{{ $data['lop_days'] ?? 0 }} day{{ (int) ($data['lop_days'] ?? 0) === 1 ? '' : 's' }}</td>
        </tr>
    </tbody>
</table>

<table class="doc__columns">
    <tbody>
        <tr>
            <td class="doc__col--earnings">
                <table class="doc__heads">
                    <thead>
                        <tr>
                            <th>Earnings</th>
                            <th class="amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($earnings as $line)
                            <tr>
                                <td>{{ $line['label'] }}</td>
                                <td class="amount">{{ $line['amount_formatted'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="doc__empty" colspan="2">No earnings this month</td>
                            </tr>
                        @endforelse
                        <tr class="doc__total">
                            <td>Gross earnings</td>
                            <td class="amount">{{ $data['gross_earnings_formatted'] ?? '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
            <td class="doc__col--deductions">
                <table class="doc__heads">
                    <thead>
                        <tr>
                            <th>Deductions</th>
                            <th class="amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deductions as $line)
                            <tr>
                                <td>{{ $line['label'] }}</td>
                                <td class="amount">{{ $line['amount_formatted'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="doc__empty" colspan="2">No deductions this month</td>
                            </tr>
                        @endforelse
                        <tr class="doc__total">
                            <td>Total deductions</td>
                            <td class="amount">{{ $data['total_deductions_formatted'] ?? '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>

<table class="doc__net">
    <tbody>
        <tr>
            <td class="doc__net__label">{{ $isRecovery ? 'Net recoverable from the employee' : 'Net pay' }}</td>
            <td class="doc__net__amount">{{ $data['net_pay_formatted'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="doc__net__words" colspan="2">{{ $data['net_pay_in_words'] ?? '' }}</td>
        </tr>
    </tbody>
</table>

@if (count($employer))
    <div class="doc__annexure">
        <p class="doc__annexure__heading">Employer contributions</p>
        <p class="doc__annexure__note">Shown for information. These amounts are not deducted from the employee.</p>
        <table>
            <tbody>
                @foreach ($employer as $line)
                    <tr>
                        <td>{{ $line['label'] }}</td>
                        <td class="amount">{{ $line['amount_formatted'] }}</td>
                    </tr>
                @endforeach
                <tr class="doc__total">
                    <td>Total employer contributions</td>
                    <td class="amount">{{ $data['total_employer_contributions_formatted'] ?? '' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
@endif

@if (count($notes))
    <div class="doc__notes">
        <strong>Notes</strong>
        <ul>
            @foreach ($notes as $note)
                <li>{{ $note }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (count($result->steps()))
    <div class="doc__working">
        <strong>How this was worked out</strong>
        <table>
            <tbody>
            @foreach ($result->steps() as $step)
                <tr>
                    <th scope="row">{{ $step->label }}@if ($step->formula)<br><small>{{ $step->formula }}</small>@endif</th>
                    <td class="amount">{{ $step->amount?->format() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if (count($result->citations()))
    <div class="doc__citations">
        @foreach ($result->citations() as $citation)
            <div>{{ $citation }}</div>
        @endforeach
    </div>
@endif

<div class="doc__footer">
    Generated by Payslip Generator (crmleaf/payslip-generator). This is a computer-generated document.
    Payment of Wages Act 1936, section 13A and the Code on Wages 2019 - the particulars a wage slip must carry - with EPF, ESI and professional tax deducted per their own enactments.
</div>

</body>
</html>
