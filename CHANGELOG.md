# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-14

### Added

- `Rules\Username` format rule: lowercase English letters, digits, hyphens, underscores and dots,
  2–30 characters, every part configurable in the config file or on the rule.
- `Rules\NotReserved` rule backed by a configurable list of reserved English and Turkish names.
- `UsernameGenerator::normalize()` and `UsernameGenerator::generate()` to turn any text into a free
  username that passes the format rule.
- `rules.numeric` option to reject usernames made of digits only.
- `Concerns\HasUsername` model trait that generates a missing username and lowercases a changed one;
  the column defaults to `username` and can be set with a `$usernameColumn` property. A generated
  username that loses a race on the unique index is regenerated and retried up to three times.
- `Events\UsernameChanged`, dispatched after commit when a saved username changes.
- `usernameChanging` and `usernameChanged` model events for observers; `usernameChanging` can
  cancel the save.
- `username` and `not_reserved_username` string aliases; the latter takes extra names as parameters.
- Turkish and English messages, publishable config and translations.

[1.0.0]: https://github.com/X-Laravel/username/releases/tag/v1.0.0
