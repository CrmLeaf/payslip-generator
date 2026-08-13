<!--
    Thanks for this. Two things worth knowing before you fill it in:

    Statutory rates and the shared arithmetic are not in this package - they are
    in crmleaf/payroll-core. A wrong figure or a changed rate is almost always a
    change there.

    A change to scaffolding shared with the fourteen sibling tools is carried
    back into their template by a maintainer, and a change to a published figure
    is mirrored into a TypeScript port that has to agree to the paisa. You do not
    need to do either; it is why those pull requests take longer to land.
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
