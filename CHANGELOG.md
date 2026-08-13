# Changelog

Notable changes to `crmleaf/payslip-generator`.

Format per [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning
per [Semantic Versioning](https://semver.org/spec/v2.0.0.html) - with one extra
rule this package observes, because it computes statutory figures:

> **Any change that alters a published result is at minimum a minor release**,
> and is listed under `Changed` with the notification, circular or Act section
> that prompted it.

## [Unreleased]

### Changed

- The PDF is a wage slip rather than a dump of every `toArray()` key: earnings and deductions print as two columns, net pay is written in words, and the employer's contributions sit in an annexure that is not deducted from the net.

## [1.0.0] - 2026-08-12

### Added

- Initial release. Builds a payslip for one employee for one wage month: earnings from the salary structure, statutory deductions computed by the core engine, and a net pay figure with the working attached to every line.

### Statutory basis

- Payment of Wages Act 1936, section 13A and the Code on Wages 2019 - the particulars a wage slip must carry - with EPF, ESI and professional tax deducted per their own enactments.

[Unreleased]: https://github.com/crmleaf/payslip-generator/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/crmleaf/payslip-generator/releases/tag/v1.0.0
