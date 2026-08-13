# Contributing to Payslip Generator

Thanks for taking the time. This package is small on purpose - it wraps one
calculator - so most contributions are either a corrected figure or a new edge
case.

By participating you agree to abide by the [Code of Conduct](CODE_OF_CONDUCT.md).

## Pull requests go to the monorepo

This repository is published automatically from
[crmleaf/payroll-tools](https://github.com/crmleaf/payroll-tools) by
`git subtree split`, and **its history is rewritten on every push to `main`**. A
commit made here is erased by the next split, so there is nothing this repository
can merge.

Open pull requests at
[crmleaf/payroll-tools](https://github.com/crmleaf/payroll-tools/pulls), where
this package lives under `tools/payslip-generator/` alongside one test suite and one set
of statutory rate tables.

Issues **are** read here, because this is where Packagist and npm send people.
They get triaged and moved.

## Reproducing locally

Cloning this package on its own is the quickest way to confirm a figure before
reporting it. Requires **PHP 8.2+** and **Composer 2**.

```bash
git clone https://github.com/crmleaf/payslip-generator.git
cd payslip-generator
composer install
composer test
```

`composer check` runs everything CI runs - code style, static analysis at level 8,
then the tests. A green run here should mean a green pull request.

The statutory maths lives in [`crmleaf/payroll-core`][core], which this package
requires. `Crmleaf\Payroll\Calculators\PayslipGenerator` is the class doing the work; everything else
here is the Laravel wiring and the browser view around it.

## Reporting a wrong figure

This is the most valuable kind of report and it has its own issue template. The
single most useful thing you can give us is **the rule that says we are wrong**:

- the inputs you used
- the figure you got
- the figure you expected
- the section, notification or circular that supports it

Inputs plus expected output plus a citation becomes a test case, and a test case
becomes a fix. Without the citation we are comparing opinions.

The statutory basis for this tool is:

> Payment of Wages Act 1936, section 13A and the Code on Wages 2019 - the particulars a wage slip must carry - with EPF, ESI and professional tax deducted per their own enactments.

## Changing a rate

Rates are **not** in this package. They live in dated tables in
[`crmleaf/payroll-core`][core], so a figure recomputed for an earlier period
still returns that period's answer. Open the issue there.

## What a change looks like

Made in the monorepo, against `tools/payslip-generator/`:

- Branch from `main`, named `feat/…`, `fix/…`, `docs/…` or `chore/…`.
- **Write a test.** A fix needs a test that fails before it.
- Assert on paise or rupee floats, never on formatted strings - the formatting
  is presentation and may change.
- One logical change per pull request.
- Update `CHANGELOG.md` under `## [Unreleased]`.

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org):

```
fix(payslip): <what changed>
test(payslip): <the case now covered>
```

## Licence

Contributions are accepted under the [MIT Licence](LICENSE), the same terms the
project is distributed under. By opening a pull request you confirm you have the
right to submit the work under that licence, and that you are not knowingly
contributing anyone else's copyrighted material.

There is no separate contributor licence agreement to sign.

## Semantic versioning, with one extra rule

This package is installed into other people's payroll systems. On top of
[semver](https://semver.org): **any change that alters a published figure is at
minimum a minor release**, and its changelog entry names the notification or
statute that prompted it.

[core]: https://github.com/crmleaf/payroll-core
