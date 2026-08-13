<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Payslip Generator' }}</title>
    <meta name="description" content="Compliance-ready payslip PDFs with your company branding.">
    <style>
        :root { color-scheme: light dark; --crmleaf-ink: #16181d; --crmleaf-muted: #5d636e; --crmleaf-line: #d9dde4; --crmleaf-accent: #1f6feb; --crmleaf-bg: #ffffff; }
        @media (prefers-color-scheme: dark) {
            :root { --crmleaf-ink: #e8eaed; --crmleaf-muted: #9aa1ad; --crmleaf-line: #2c313a; --crmleaf-accent: #6ea8ff; --crmleaf-bg: #14161a; }
        }
        body { margin: 0; padding: 2rem 1rem; background: var(--crmleaf-bg); color: var(--crmleaf-ink);
               font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .crmleaf-tool { max-width: 46rem; margin: 0 auto; }
        .crmleaf-tool__heading { font-size: 1.5rem; margin: 0 0 .25rem; }
        .crmleaf-tool__tagline { color: var(--crmleaf-muted); margin: 0 0 1.5rem; }
        .crmleaf-tool__error { border-left: 3px solid #c0392b; padding: .5rem .75rem; background: rgba(192,57,43,.08); }
        .crmleaf-field { display: block; margin: 0 0 1rem; }
        .crmleaf-field > span { display: block; font-weight: 600; margin-bottom: .25rem; }
        .crmleaf-field input:not([type=checkbox]), .crmleaf-field select, .crmleaf-field textarea {
            width: 100%; padding: .5rem .6rem; border: 1px solid var(--crmleaf-line); border-radius: .35rem;
            background: transparent; color: inherit; font: inherit; }
        .crmleaf-field small { display: block; color: var(--crmleaf-muted); margin-top: .25rem; }
        .crmleaf-field--bool { display: flex; align-items: flex-start; gap: .5rem; }
        .crmleaf-tool__submit { padding: .6rem 1.2rem; border: 0; border-radius: .35rem;
            background: var(--crmleaf-accent); color: #fff; font: inherit; cursor: pointer; }
        .crmleaf-tool__figures { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
        .crmleaf-tool__figures th, .crmleaf-tool__figures td { text-align: left; padding: .4rem 0; border-bottom: 1px solid var(--crmleaf-line); }
        .crmleaf-tool__figures td { text-align: right; font-variant-numeric: tabular-nums; }
        .crmleaf-tool__working ol { padding-left: 1.2rem; }
        .crmleaf-tool__working li { margin-bottom: .6rem; }
        .crmleaf-step__amount { float: right; font-variant-numeric: tabular-nums; }
        .crmleaf-step__formula, .crmleaf-step__citation { display: block; color: var(--crmleaf-muted); font-size: .85rem; }
        .crmleaf-tool__citations { color: var(--crmleaf-muted); font-size: .85rem; padding-left: 1.2rem; }
        .crmleaf-tool__colophon { max-width: 46rem; margin: 2.5rem auto 0; padding-top: 1rem;
            border-top: 1px solid var(--crmleaf-line); color: var(--crmleaf-muted); font-size: .85rem; }
    </style>
</head>
<body>

<x-crmleaf::payslip-generator
    :action="$action ?? null"
    :defaults="$defaults ?? []"
    :input="$input ?? []"
    :result="$result ?? null"
    :error="$error ?? null"
    :heading="$title ?? 'Payslip Generator'"
/>

<footer class="crmleaf-tool__colophon">
    <p>
        Payment of Wages Act 1936, section 13A and the Code on Wages 2019 - the particulars a wage slip must carry - with EPF, ESI and professional tax deducted per their own enactments.
    </p>
    <p>
        Computed by <a href="https://github.com/crmleaf/payslip-generator">crmleaf/payslip-generator</a>,
        MIT licensed. A calculation library, not tax advice - verify against your own
        compliance obligations before filing.
    </p>
</footer>

@if (config('payslip-generator.assets.script', false))
    <script src="{{ asset('vendor/payslip-generator/payslip-generator.js') }}" defer></script>
@endif

</body>
</html>
