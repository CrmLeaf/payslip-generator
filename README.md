# Payslip Generator

Compliance-ready payslip PDFs with your company branding.

Builds a payslip for one employee for one wage month: earnings from the salary structure, statutory deductions computed by the core engine, and a net pay figure with the working attached to every line.

Part of the [CRMLeaf payroll tools](https://github.com/crmleaf/payroll-tools)
suite. The arithmetic lives in `crmleaf/payroll-core`; this package is the thin
skin that makes it installable, mountable and embeddable on its own.

> [!NOTE]
> This repository is a **read-only mirror**, published by `git subtree split` from
> [crmleaf/payroll-tools](https://github.com/crmleaf/payroll-tools). Pull requests
> belong there, where this package is developed against one test suite and one set
> of statutory rate tables. Issues are read here too, since this is where
> Packagist and npm send people.

## Install

**Composer** - Laravel auto-discovers the service provider, so this is the whole
setup:

```bash
composer require crmleaf/payslip-generator
```

**npm** - the same calculation, re-exported from `@crmleaf/payroll-js` so you can
install this one tool and nothing else:

```bash
npm install @crmleaf/payslip-generator
```

**A plain script tag** - no build step, no bundler, no server. Build the browser
bundle once and serve the file yourself:

```html
<script src="/js/payroll.min.js"></script>
<script>
const result = CrmleafPayroll.payslip({
  employeeName: "Asha Menon",
  monthlyGross: 75000,
  monthlyBasic: 30000,
  payMonth: "2025-08-01",
});
console.log(result.explain);
</script>
```

`payroll.min.js` is the single-file browser build. Get it by running
`npm run build` in [`@crmleaf/payroll-js`][js] and copying `dist/payroll.min.js`
into whatever your site serves as static assets.

> A hosted CDN build is coming soon, which will reduce this to a single URL.
> Serving the file yourself works today and keeps working afterwards - it is the
> only option that needs no third-party request, so plenty of projects will want
> to stay on it.

## Use it

**Plain PHP**, no framework and no container:

```php
use Crmleaf\Payroll\Calculators\PayslipGenerator;
use Crmleaf\Payroll\Money;

$result = (new PayslipGenerator())->calculate(
    employeeName: 'Asha Menon',
    monthlyGross: Money::fromRupees(75_000),
    monthlyBasic: Money::fromRupees(30_000),
    payMonth: new \DateTimeImmutable('2025-08-01'),
);

echo $result->explain();      // the formula with the real operands in it
echo $result->workings();     // every step, one per line, with its citation
print_r($result->toArray());  // snake_case, ready for JSON
```

**Laravel** - resolve it from the container, or type-hint it anywhere:

```php
use Crmleaf\Payroll\Calculators\PayslipGenerator;

public function show(PayslipGenerator $calculator)
{
    return $calculator->calculate(
        employeeName: 'Asha Menon',
        monthlyGross: Money::fromRupees(75_000),
        monthlyBasic: Money::fromRupees(30_000),
        payMonth: new \DateTimeImmutable('2025-08-01'),
    )->toArray();
}
```

**Blade** - one component, no controller:

```blade
<x-crmleaf::payslip-generator />
```

**HTTP** - off by default. Publish the config and turn the route on:

```bash
php artisan vendor:publish --tag=payslip-generator-config
```

```php
// config/payslip-generator.php
'route' => ['enabled' => true, 'prefix' => 'tools'],
```

```bash
curl -X POST https://example.test/tools/payslip-generator \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"employee_name":"Asha Menon","monthly_gross":75000,"monthly_basic":30000,"pay_month":"2025-08-01"}'
```

The JSON response carries the figures, the working and the statutory citations:

```json
{
  "tool": "payslip-generator",
  "data": { "…": "every figure, snake_case, with a *_formatted twin" },
  "explain": "the formula with the real operands substituted",
  "working": [{ "label": "…", "amount": 0, "formula": "…", "citation": "…" }],
  "citations": ["…"]
}
```

**JavaScript**:

```js
import { payslip } from '@crmleaf/payslip-generator';

const result = payslip({
  employeeName: "Asha Menon",
  monthlyGross: 75000,
  monthlyBasic: 30000,
  payMonth: "2025-08-01",
});
```

## Documents render inside your application

This tool writes a PDF file, which means it needs a server.
Rendering happens in **your** application, against **your** published config, and
the bytes never leave your infrastructure - there is no hosted document service
and therefore no credential for a browser to carry.

```bash
composer require dompdf/dompdf
php artisan vendor:publish --tag=payslip-generator-config   # company name, address, GSTIN, logo
```

```php
use Crmleaf\Payroll\Tools\PayslipGenerator\Documents\PayslipGeneratorDocument;

return app(PayslipGeneratorDocument::class)->download($result, 'payslip-generator.pdf');
```

Add `format=pdf` to the HTTP request and the route returns the
file directly.

## Inputs

| Field | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| `employee_name` | string | Yes | `"Asha Menon"` |  |
| `employee_code` | string | No | `"EMP-0001"` |  |
| `designation` | string | No | `"Senior Engineer"` |  |
| `monthly_gross` | money (₹) | Yes | `75000` |  |
| `monthly_basic` | money (₹) | Yes | `30000` |  |
| `pay_month` | date (YYYY-MM-DD) | Yes | `"2025-08-01"` | Any date within the month being paid; the first of the month is conventional. |
| `days_payable` | integer | No | `31` |  |
| `lop_days` | integer | No | `0` |  |
| `state` | string | No | `"Karnataka"` | Decides which professional tax schedule applies. |
| `as_of` | date (YYYY-MM-DD) | No | - | Leave blank for current rates. Set it to recompute an old payslip on the rates that were in force then. |

Optional fields you leave out are omitted from the call entirely, so the
calculator's own documented defaults apply.

Every figure here rests on a statutory rate, so the call takes `as_of`. Set it
and the calculation runs on the rates in force on that date, which is what makes
a prior year recomputable rather than merely rememberable.

## Statutory basis

Payment of Wages Act 1936, section 13A and the Code on Wages 2019 - the particulars a wage slip must carry - with EPF, ESI and professional tax deducted per their own enactments.

Rates are data, not code: they live in dated tables with a cited source in
`crmleaf/payroll-core`, so a rate change is a new dated entry rather than an edit
to a constant.

> [!IMPORTANT]
> This package implements our reading of the applicable statutes and is provided
> without warranty. It is a calculation library, not tax advice. Verify against
> your own compliance obligations before relying on the output for statutory
> filing.

## Publishing

| Tag | Publishes |
|-----|-----------|
| `payslip-generator-config` | `config/payslip-generator.php` |
| `payslip-generator-views` | `resources/views/vendor/payslip-generator` |
| `payslip-generator-assets` | `public/vendor/payslip-generator` |

## Licence

[MIT](LICENSE) © CRMLeaf. Use it commercially, embed it, fork it.

[js]: https://github.com/crmleaf/payroll-js
