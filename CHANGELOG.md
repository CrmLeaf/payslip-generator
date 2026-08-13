# Changelog

Notable changes to `crmleaf/payslip-generator`.

Format per [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning
per [Semantic Versioning](https://semver.org/spec/v2.0.0.html) - with one extra
rule this package observes, because it computes statutory figures:

> **Any change that alters a published result is at minimum a minor release**,
> and is listed under `Changed` with the notification, circular or Act section
> that prompted it.

## [Unreleased]

## [1.1.0] - 2026-08-13

### Changed

- The PDF is a wage slip rather than a dump of every `toArray()` key: earnings and deductions print as two columns, net pay is written in words, and the employer's contributions sit in an annexure that is not deducted from the net.

### Added

- The generator is covered when called with no date at all. Every other test pins a date, so the path a first-time caller takes was never exercised, and this package reads four rate tables - if any of them stops covering today, that path throws while the suite stays green.

### Fixed

- Documentation described this repository as a read-only mirror into which pull requests could not be merged, and routed security reports to a repository nobody outside the organisation could reach. Issues, pull requests and advisories all belong here.
- The readme told people to run `composer require crmleaf/payslip-generator`, which cannot work until the package is on Packagist. It now carries a route that works today.

## [1.0.0] - 2026-08-12

### Added

- Initial release. Builds a payslip for one employee for one wage month: earnings from the salary structure, statutory deductions computed by the core engine, and a net pay figure with the working attached to every line.

### Statutory basis

- Payment of Wages Act 1936, section 13A and the Code on Wages 2019 - the particulars a wage slip must carry - with EPF, ESI and professional tax deducted per their own enactments.

[Unreleased]: https://github.com/crmleaf/payslip-generator/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/crmleaf/payslip-generator/releases/tag/v1.0.0
