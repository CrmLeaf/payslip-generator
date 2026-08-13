# Security Policy

## Reporting a vulnerability

**Please do not open a public issue.**

Use the [Report a vulnerability][advisories] button on the monorepo's Security
tab, which opens a private thread, or email <security@crmleaf.app> with
"SECURITY" in the subject.

Reports go to [crmleaf/payroll-tools][advisories] rather than here, because that
is where the fix is made and where private advisory reporting is enabled.

Include the version affected, the impact, and steps to reproduce.

### What to expect

- **Acknowledgement** within 3 business days.
- **Severity assessment** within 10 business days.
- **Coordinated disclosure**, 90 days by default - sooner if a fix ships sooner.

Reporters are credited in the advisory unless they ask not to be.

## A wrong figure is not a vulnerability

An incorrect statutory calculation is a **bug**, and a serious one, but it is not
a security issue - please report it as a normal issue so it can be discussed in
the open. The [Incorrect calculation][calc] template asks for the citation that
turns it into a test case.

## Scope

In scope: this package, `crmleaf/payslip-generator`.

Out of scope: the hosted tools at <https://www.indpayroll.com/free-tools>, and
third-party dependencies - report those upstream, and tell us so we can pin or
patch.

[advisories]: https://github.com/crmleaf/payroll-tools/security/advisories/new
[calc]: https://github.com/crmleaf/payslip-generator/issues/new?template=calculation-error.yml
