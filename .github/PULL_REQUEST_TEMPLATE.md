<!--
    This repository is published from crmleaf/payroll-tools by git subtree split,
    and its history is rewritten on every push. A pull request opened here cannot
    be merged - the next split erases it.

    Open it at https://github.com/crmleaf/payroll-tools/pulls instead, against
    tools/payslip-generator/. The checklist below is the one used there.
-->

## What this changes

<!-- One or two sentences. Link the issue: "Fixes #12". -->

## Type

- [ ] Bug fix (no change to any published figure)
- [ ] Corrected calculation (**changes output** - see below)
- [ ] New feature
- [ ] Documentation
- [ ] Chore / CI

## Checklist

- [ ] A test covers this, and it fails without the change
- [ ] `composer test` passes
- [ ] `CHANGELOG.md` updated under `## [Unreleased]`
- [ ] No credential, token or key is committed

## If this changes a published figure

<!-- Delete if it does not. -->

- [ ] The statutory basis is cited below
- [ ] A test pins the new behaviour
- [ ] Anything version-dependent is handled in `crmleaf/payroll-core`'s dated
      rate tables rather than branched on here

**Statutory basis:**

<!-- The section, notification or circular. Reviewers should not have to hunt. -->

**Worked example:**

<!--
Inputs → output, before and after. The fastest way for a reviewer to confirm
the change is right.

Before: <inputs> → <old figure>
After:  <inputs> → <new figure>
-->
