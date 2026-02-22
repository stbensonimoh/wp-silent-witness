# Contributing to WP Silent Witness

Thank you for your interest in contributing. This document covers the conventions required to keep the project's automated changelog and versioning working correctly.

## Conventional Commits

This project uses [Conventional Commits](https://www.conventionalcommits.org/) to drive automated changelog generation and semantic versioning via [Release Please](https://github.com/googleapis/release-please).

**Every commit message merged into `main` must follow the Conventional Commits specification.**

### Format

```
<type>[optional scope]: <short description>

[optional body]

[optional footer(s)]
```

### Commit Types

| Type | Description | Version bump |
|------|-------------|--------------|
| `feat` | A new feature visible to users | **minor** (`x.Y.0`) |
| `fix` | A bug fix | **patch** (`x.y.Z`) |
| `perf` | A performance improvement | **patch** (`x.y.Z`) |
| `revert` | Reverts a previous commit | **patch** (`x.y.Z`) |
| `docs` | Documentation changes only | none |
| `style` | Code style / formatting (no logic change) | none |
| `refactor` | Code restructuring without feature or fix | none |
| `test` | Adding or updating tests | none |
| `build` | Build system or external dependencies | none |
| `ci` | CI configuration changes | none |
| `chore` | Other maintenance tasks | none |

### Breaking Changes

To trigger a **major** version bump (`X.0.0`), add `BREAKING CHANGE:` in the commit footer, or append `!` after the type:

```
feat!: remove support for PHP 7.4

BREAKING CHANGE: The minimum PHP version is now 8.0.
```

### Examples

```
feat: add admin page with Ingest Now button
fix: reset log offset when debug.log is truncated
perf: replace SELECT + INSERT with ON DUPLICATE KEY UPDATE
docs: add inline PHPDoc blocks to all public methods
chore: update PHPCS ruleset to WordPress 3.1
ci: add release-please workflow for automated changelog
feat(cli): add export command to output logs as JSON
fix(cron)!: rename cron hook to avoid collision with third-party plugins

BREAKING CHANGE: sites must re-register the cron event after upgrading.
```

## Pull Request Workflow

1. Fork the repository and create a branch from `main`.
2. Make your changes with properly formatted commit messages.
3. Open a pull request against `main`.
4. All commits in the PR must comply with Conventional Commits — the PR title is used as the squash-merge commit message, so it must also follow the format.

## Automated Releases

When a PR following Conventional Commits is merged into `main`, Release Please will:

1. Open (or update) a **Release PR** that bumps the version in:
   - `composer.json`
   - `wp-silent-witness.php` (`Version:` header)
   - `readme.txt` (`Stable tag:`)
   - `CHANGELOG.md` (auto-generated)
2. When the Release PR is merged, a GitHub Release and git tag are created automatically.

You do **not** need to manually edit version numbers or `CHANGELOG.md`.

## Code Style

This project follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/). Run the linter before submitting:

```bash
composer run phpcs
```

Auto-fix where possible:

```bash
composer run phpcbf
```
