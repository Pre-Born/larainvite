# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [13.1.0] - 2026-10-22

### Added

- Test suite built on Orchestra Testbench, runnable with `composer test`.
- "Upgrading to v13.1" section in the README.

### Changed

- PHP 8.1 or newer is now required.
- `Invited`, `InvitationConsumed`, `InvitationCanceled` and `InvitationExpired` are dispatched after the
  surrounding database transaction commits, and are not dispatched if it rolls back. Outside a transaction they
  are still dispatched immediately.
- `isAllowed()` compares emails case-insensitively and ignores surrounding whitespace.
- The published migration creates a unique index on `code` instead of a plain index. Existing installs are not
  changed; see the README upgrade notes.

### Fixed

- `consume()` and `cancel()` are atomic: when two requests use the same code at once, only one succeeds, and a
  cancel can no longer overwrite an invitation that was just consumed.
- Invitations that were already `successful` or `canceled` are no longer changed to `expired` once `valid_till`
  passes.
- A missing or malformed `valid_till` now counts as expired instead of throwing or never expiring.
- `setCode()` clears the previous lookup, so in long-running processes (Octane, queue workers) an unknown code no
  longer returns the previously loaded invitation.
- The event class names in the README (`InvitationCanceled`, `InvitationExpired`).

### Security

- Invitation codes are 40 random alphanumeric characters from a cryptographically secure generator, replacing
  predictable `md5(uniqid())` hashes. Existing codes keep working, but custom `code` columns narrower than 40
  characters must be widened.

## [13.0.0] - 2026-05-14

### Added

- Support for Laravel 13.

## [12.0.0] - 2025-03-12

### Added

- Support for Laravel 12.

## [7.0.0] - 2024-04-18

### Added

- Support for Laravel 11.

Releases before 7.0.0 are listed on the
[upstream repository](https://github.com/junaidnasir/larainvite/tags).

[Unreleased]: https://github.com/Pre-Born/larainvite/compare/v13.1.0...HEAD
[13.1.0]: https://github.com/Pre-Born/larainvite/compare/v13.0.0...v13.1.0
[13.0.0]: https://github.com/Pre-Born/larainvite/releases/tag/v13.0.0
[12.0.0]: https://github.com/junaidnasir/larainvite/compare/v7.0.0...v12.0.0
[7.0.0]: https://github.com/junaidnasir/larainvite/releases/tag/v7.0.0
