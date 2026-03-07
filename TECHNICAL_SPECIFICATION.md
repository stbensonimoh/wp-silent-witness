# Technical Specification: Silent Witness for WordPress v4.0.0

**Status:** Draft  
**Author:** Benson Imoh  
**Date:** 2026-03-07  
**Target Version:** 4.0.0  
**WordPress Version:** 6.0+  
**PHP Version:** 8.1 - 8.4  
**Distribution:** WordPress.org Plugin Directory  

---

## 1. Overview

### 1.1 Purpose

This document specifies the technical requirements for Silent Witness for WordPress v4.0.0, a high-performance error log ingestion and deduplication plugin. This version introduces breaking changes including PHP 8.1+ requirement, WordPress.org compliance, and resolution of all identified PHP 8.4 compatibility issues.

### 1.2 Goals

- Provide zero-cost, high-performance log ingestion from WordPress debug.log
- Implement hash-based deduplication to minimize database footprint
- Offer structured JSON export via WP-CLI for analysis and monitoring
- Ensure full compatibility with PHP 8.1 through 8.4
- Achieve WordPress.org plugin directory compliance
- Support WordPress Multisite installations

### 1.3 Non-Goals

- This release SHALL NOT add support for additional log sources beyond debug.log
- This release SHALL NOT include a web-based admin interface
- This release SHALL NOT support PHP versions below 8.1
- This release SHALL NOT provide migration paths from previous installations (clean break)

---

## 2. Technical Requirements

The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD", "SHOULD NOT", "RECOMMENDED", "MAY", and "OPTIONAL" in this document are to be interpreted as described in [RFC 2119](https://datatracker.ietf.org/doc/html/rfc2119).

### 2.1 PHP Requirements

**[REQ-PHP-001]** The plugin MUST require PHP version 8.1 or higher.

**[REQ-PHP-002]** The plugin MUST be compatible with PHP versions 8.1, 8.2, 8.3, and 8.4.

**[REQ-PHP-003]** The plugin MUST NOT use deprecated functions or features removed in PHP 8.x.

**[REQ-PHP-004]** The plugin MUST handle `filesize()` return values of `int|false` per PHP 8.x type semantics (Issue #20).

**[REQ-PHP-005]** The plugin MUST validate `json_encode()` return values before output (Issue #21).

**[REQ-PHP-006]** The plugin MUST use `hash('xxh3', ...)` for non-cryptographic deduplication hashing (already implemented in v3.0.0).

### 2.2 WordPress Requirements

**[REQ-WP-001]** The plugin MUST require WordPress version 6.0 or higher.

**[REQ-WP-002]** The plugin MUST support WordPress Multisite installations.

**[REQ-WP-003]** The plugin MUST use `$wpdb->base_prefix` consistently for all database table references (Issue #23).

**[REQ-WP-004]** The plugin MUST implement proper uninstall routines via `uninstall.php`.

**[REQ-WP-005]** The plugin MUST use WordPress Coding Standards (WPCS) compliant code style.

**[REQ-WP-006]** The plugin MUST implement internationalization (i18n) using `__()` and `_e()` functions with a unique text domain.

### 2.3 WordPress.org Compliance

**[REQ-ORG-001]** The plugin name MUST be changed from "WP Silent Witness" to "Silent Witness for WordPress" (Issue #18).

**[REQ-ORG-002]** The plugin slug MUST be updated to `silent-witness-for-wordpress`.

**[REQ-ORG-003]** The plugin headers MUST include all required fields: Plugin Name, Plugin URI, Description, Version, Author, Author URI, License, License URI, Text Domain, Domain Path.

**[REQ-ORG-004]** The readme.txt Contributors field MUST use a valid WordPress.org username (Issue #19).

**[REQ-ORG-005]** The plugin MUST pass the WordPress.org plugin validator without fatal errors or warnings.

**[REQ-ORG-006]** The plugin license MUST be GPLv2 or later (consistent with WordPress core).

---

## 3. Architecture

### 3.1 Plugin Structure

```
silent-witness-for-wordpress/
├── silent-witness-for-wordpress.php    # Main plugin file
├── uninstall.php                        # Uninstall routine
├── readme.txt                           # WordPress.org readme
├── CHANGELOG.md                         # Changelog
├── CONTRIBUTING.md                      # Contribution guidelines
├── LICENSE                              # GPL v2 license
├── composer.json                        # Composer dependencies
├── .phpcs.xml                          # PHP CodeSniffer config
├── .github/
│   └── workflows/
│       ├── php-lint.yml                # CI linting
│       └── release-please.yml          # Automated releases
├── languages/                          # Translation files (optional)
├── tests/                              # Test suite
│   ├── unit/
│   ├── integration/
│   └── bootstrap.php
└── docs/                               # Documentation
```

### 3.2 Class Architecture

**[REQ-ARCH-001]** The plugin MUST implement a singleton pattern for the main class `Silent_Witness_For_WordPress`.

**[REQ-ARCH-002]** The main class MUST handle:

- Database table lifecycle (creation, upgrades)
- Cron schedule registration and management
- WP-CLI command registration
- Text domain loading

**[REQ-ARCH-003]** The plugin MUST use WordPress hooks appropriately:

- `init` action for text domain loading
- `cron_schedules` filter for custom schedule registration
- `silent_witness_cron_ingest` action for scheduled ingestion

---

## 4. Database Schema

### 4.1 Table Definition

**[REQ-DB-001]** The plugin MUST create a single custom database table `{base_prefix}silent_witness_logs`.

**[REQ-DB-002]** The table schema MUST be:

```sql
CREATE TABLE `{base_prefix}silent_witness_logs` (
    hash CHAR(16) NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    file VARCHAR(255) NOT NULL,
    line INT UNSIGNED NOT NULL,
    count INT UNSIGNED DEFAULT 1,
    first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (hash),
    INDEX idx_last_seen (last_seen)
) {charset_collate};
```

**[REQ-DB-003]** The `hash` column MUST use `CHAR(16)` to accommodate PHP `hash('xxh3', ...)` hex output (16 characters).

**[REQ-DB-004]** The plugin MUST use `ON DUPLICATE KEY UPDATE` for atomic deduplication:

- Increment `count` column by 1
- Update `last_seen` timestamp automatically

**[REQ-DB-005]** The plugin MUST store file paths as slash-separated paths relative to `ABSPATH`, without a leading slash.

### 4.2 Options Management

**[REQ-OPT-001]** The plugin MUST use site options for shared state:

- `silent_witness_log_offset` - File offset for incremental log reading
- `silent_witness_db_version` - Database schema version

**[REQ-OPT-002]** Options MUST be stored using `update_site_option()` and retrieved using `get_site_option()`.

On single-site installations, WordPress will persist these values in the site's options table. On Multisite installations, WordPress will persist them network-wide via the site option APIs.

**[REQ-OPT-003]** Options MUST be cleaned up on uninstall using `delete_site_option()`.

---

## 5. Log Ingestion Engine

### 5.1 Log Parsing

**[REQ-INGEST-001]** The plugin MUST read from `WP_CONTENT_DIR . '/debug.log'`.

**[REQ-INGEST-002]** The plugin MUST use `fseek()` for efficient incremental reading based on stored offset.

**[REQ-INGEST-003]** The plugin MUST handle log rotation by resetting offset when `filesize() < stored_offset`.

**[REQ-INGEST-004]** The plugin MUST guard against `filesize()` returning `false` (Issue #20):

```php
$file_size = filesize($this->log_path);
if (false === $file_size) {
    fclose($handle);
    return __('Could not determine log file size.', 'silent-witness-for-wordpress');
}
```

**[REQ-INGEST-005]** The plugin MUST parse log lines matching the pattern:

```
[timestamp] PHP {type}: {message} in {file} on line {line}
```

**[REQ-INGEST-006]** Regex pattern MUST be:

```
/^\[[^\]]+\] PHP ([^:]+):  (.+?) in (.+?) on line (\d+)/
```

### 5.2 Hash Generation

**[REQ-HASH-001]** Hash MUST be generated using:

```php
$hash = hash('xxh3', implode('|', [$type, $message, $clean_file, (string)$line]));
```

**[REQ-HASH-002]** Components MUST be joined with pipe delimiter `|` to prevent collision (e.g., type="A", message="Bfile" vs type="AB", message="file").

### 5.3 Cron Scheduling

**[REQ-CRON-001]** The plugin MUST register a custom cron schedule 'quarterly' (15 minutes / 900 seconds).

**[REQ-CRON-002]** The plugin MUST schedule `silent_witness_cron_ingest` event on initialization if not already scheduled.

**[REQ-CRON-003]** The plugin MUST clear scheduled events on uninstall.

---

## 6. WP-CLI Interface

### 6.1 Command Structure

**[REQ-CLI-001]** The plugin MUST register the command: `wp silent-witness`

**[REQ-CLI-002]** The following subcommands MUST be implemented:

| Command | Description |
|---------|-------------|
| `ingest` | Manually trigger log ingestion from debug.log |
| `export` | Output all logs as formatted JSON to stdout |
| `clear` | Truncate logs table and reset file offset |
| `destroy --yes` | Completely remove table and cleanup (requires confirmation) |

### 6.2 Command Specifications

**[REQ-CLI-003]** `ingest` command:

- MUST output progress message
- MUST output success message with count of ingested entries
- MUST handle and display error messages appropriately

**[REQ-CLI-004]** `export` command:

- MUST output valid JSON array
- MUST use `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE` flags
- MUST validate `json_encode()` return value (Issue #21):

```php
$json = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (false === $json) {
    WP_CLI::error('JSON encoding failed: ' . json_last_error_msg());
    return;
}
echo $json;
```

**[REQ-CLI-005]** `clear` command:

- MUST truncate the logs table
- MUST reset `silent_witness_log_offset` option to 0
- MUST output confirmation message

**[REQ-CLI-006]** `destroy` command:

- MUST require `--yes` flag for confirmation
- MUST drop the database table
- MUST clear scheduled cron hook
- MUST delete all plugin options
- MUST output confirmation message

---

## 7. Security Considerations

### 7.1 Data Handling

**[REQ-SEC-001]** The plugin MUST NOT log or store:

- Request context (URL, HTTP method, user ID)
- POST data or request parameters
- Cookies or session data
- Stack traces (unless contained in error message)

**[REQ-SEC-002]** The plugin MUST store only essential metadata:

- Error type (e.g., Notice, Warning, Error)
- Error message (truncated to 2000 characters)
- File path (relative to ABSPATH)
- Line number
- Deduplication counter and timestamps

### 7.2 Input Validation

**[REQ-SEC-003]** Database queries MUST use `$wpdb->prepare()` for all dynamic values.

**[REQ-SEC-004]** Table names in queries MUST be constructed only from trusted WordPress-derived values such as `$wpdb->base_prefix` and MUST NOT include user input.

**[REQ-SEC-005]** File paths MUST be normalized to slash-separated paths relative to `ABSPATH`, with no leading slash.

### 7.3 Access Control

**[REQ-SEC-006]** WP-CLI commands SHOULD check for appropriate capabilities when possible (WP-CLI context typically implies admin access).

**[REQ-SEC-007]** The plugin MUST check for `ABSPATH` constant at file entry to prevent direct access.

**[REQ-SEC-008]** `uninstall.php` MUST verify `WP_UNINSTALL_PLUGIN` constant before execution.

---

## 8. Multisite Support

The plugin MUST support both single-site WordPress installations and Multisite networks.

### 8.1 Table Management

**[REQ-MS-001]** The plugin MUST use `$wpdb->base_prefix` consistently (Issue #23):

- Main plugin: `$this->table = $wpdb->base_prefix . 'silent_witness_logs'`
- Uninstaller: `$table_name = $wpdb->base_prefix . 'silent_witness_logs'`

**[REQ-MS-002]** On Multisite, the logs table MUST be shared across all sites in the network.

**[REQ-MS-003]** On Multisite, the plugin MUST operate as a network-wide collector and be intended for network activation.

### 8.2 Options Management

**[REQ-MS-004]** The plugin MUST use the site option APIs for shared state in both single-site and Multisite environments.

On single-site, WordPress will store these values in the site's options table. On Multisite, WordPress will store them network-wide.

---

## 9. Internationalization

### 9.1 Text Domain

**[REQ-I18N-001]** The text domain MUST be `silent-witness-for-wordpress`.

**[REQ-I18N-002]** All user-facing strings MUST use WordPress i18n functions:

- `__()` for returns
- `_e()` for echoing
- `_n()` for pluralization

### 9.2 Plugin Headers

```php
/**
 * Plugin Name: Silent Witness for WordPress
 * Plugin URI:  https://github.com/stbensonimoh/silent-witness-for-wordpress
 * Description: Zero-cost, high-performance log ingestion and de-duplication for WordPress.
 * Version:     4.0.0
 * Requires PHP: 8.1
 * Requires at least: 6.0
 * Author:      Benson Imoh
 * Author URI:  https://stbensonimoh.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: silent-witness-for-wordpress
 * Domain Path: /languages
 * Network:     true
 */
```

---

## 10. Testing Requirements

### 10.1 Unit Tests (PHPUnit)

**[REQ-TEST-001]** Unit tests MUST cover:

- Hash generation with various inputs
- Log line parsing with different error formats
- File offset calculations
- Deduplication logic

**[REQ-TEST-002]** PHP 8.4 compatibility tests MUST include:

- `filesize()` false-return handling
- `json_encode()` failure scenarios

### 10.2 Integration Tests

**[REQ-TEST-003]** Integration tests MUST verify:

- Database table creation with `dbDelta()`
- `INSERT ... ON DUPLICATE KEY UPDATE` behavior
- Cron schedule registration
- WP-CLI command execution

**[REQ-TEST-004]** Multisite tests MUST include:

- `$wpdb->prefix` vs `$wpdb->base_prefix` consistency
- Uninstaller targeting correct table on sub-sites
- Site options stored and retrieved correctly through the WordPress site option APIs

### 10.3 Manual Testing Checklist

**[REQ-TEST-005]** Manual tests MUST verify WordPress.org compliance:

- Plugin validator passes without errors
- Plugin name meets guidelines
- Contributors field valid
- All headers present
- readme.txt format correct

**[REQ-TEST-006]** Manual tests MUST verify functionality:

- Debug log ingestion works
- Deduplication increments counter
- Export produces valid JSON
- Clear and destroy commands work
- Uninstall removes all data

### 10.4 CI/CD Requirements

**[REQ-TEST-007]** GitHub Actions workflows MUST:

- Run PHPCS on all PHP files
- Run PHP syntax check (`php -l`)
- Test against PHP 8.1, 8.2, 8.3, 8.4
- Fail builds on any coding standard violations

---

## 11. Open Issues Resolution

### 11.1 Issue #18: Plugin Name Restricted Term

**Status:** RESOLVED via rename  
**Implementation:** Change all references from "WP Silent Witness" to "Silent Witness for WordPress"

**Files to update:**

- Plugin header in main file
- readme.txt
- All documentation (README.md, CONTRIBUTING.md)
- composer.json name field
- Repository name (GitHub)

### 11.2 Issue #19: Invalid WordPress.org Username

**Status:** RESOLVED via update  
**Implementation:** Update Contributors field in readme.txt with valid WordPress.org username

**Action required:** Create WordPress.org account or verify existing username

### 11.3 Issue #20: filesize() False Return

**Status:** FIX REQUIRED  
**Location:** `wp-silent-witness.php` lines 214-218  
**Implementation:**

```php
$file_size = filesize($this->log_path);
if (false === $file_size) {
    fclose($handle);
    return __('Could not determine log file size.', 'silent-witness-for-wordpress');
}
```

### 11.4 Issue #21: json_encode() Output Guard

**Status:** FIX REQUIRED  
**Location:** `wp-silent-witness.php` lines 345-350  
**Implementation:**

```php
$json = wp_json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (false === $json) {
    WP_CLI::error('Failed to encode logs as JSON: ' . json_last_error_msg());
    return;
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
WP_CLI::log($json);
```

### 11.5 Issue #23: Multisite Table Prefix Mismatch

**Status:** VERIFY FIXED  
**Location:** `uninstall.php` line 16  
**Current Code:** `$table_name = $wpdb->base_prefix . 'silent_witness_logs'`  
**Status:** Already using `base_prefix` - verify consistency with main plugin

---

## 12. Migration Plan (Clean Break)

### 12.1 Version Strategy

**[REQ-MIG-001]** This release SHALL be version 4.0.0 indicating breaking changes.

**[REQ-MIG-002]** Previous installations MUST be treated as incompatible due to:

- PHP version requirement change (7.4 → 8.1)
- Plugin rename (new slug, file name, and text domain)
- Hash algorithm change (already occurred in v3.0.0)

### 12.2 Installation Requirements

**[REQ-MIG-003]** The plugin MUST document:

- PHP 8.1+ requirement prominently
- WordPress 6.0+ requirement
- Fresh installation recommended (not upgrade from previous versions)

### 12.3 Database Cleanup

**[REQ-MIG-004]** For users with previous installations, documentation MUST provide manual cleanup instructions:

- Drop old table `{prefix}silent_witness_logs` if exists
- Delete old options `silent_witness_log_offset`, `silent_witness_db_version`

---

## 13. File Structure & Naming

### 13.1 Main Plugin File

**[REQ-FILE-001]** The main plugin file MUST be named: `silent-witness-for-wordpress.php`

**[REQ-FILE-002]** The file MUST contain the complete plugin header as specified in Section 9.2.

**[REQ-FILE-003]** The file MUST define the main class `Silent_Witness_For_WordPress`.

### 13.2 Class Naming

**[REQ-FILE-004]** Class name MUST follow WordPress naming conventions with underscores.

**[REQ-FILE-005]** The class MUST be defined within the `if (!defined('ABSPATH'))` guard.

---

## 14. Documentation Requirements

### 14.1 readme.txt

**[REQ-DOC-001]** The readme.txt MUST include:

- Plugin name
- Contributors (valid WordPress.org username)
- Tags (up to 5 relevant tags)
- Requires at least: 6.0
- Tested up to: 6.9 (current stable)
- Stable tag: 4.0.0
- Requires PHP: 8.1
- License: GPLv2 or later
- Description (short and long)
- Installation instructions
- FAQ section
- Screenshots section
- Changelog

### 14.2 README.md

**[REQ-DOC-002]** README.md SHOULD provide:

- Project overview
- Requirements
- Installation instructions (Composer and manual)
- Usage examples
- Contributing guidelines
- License information

### 14.3 CHANGELOG.md

**[REQ-DOC-003]** CHANGELOG.md MUST document all breaking changes in v4.0.0 following [Keep a Changelog](https://keepachangelog.com/) format.

---

## 15. Compliance Checklist

### 15.1 WordPress.org Requirements

- [ ] Plugin name does not contain "WP" prefix
- [ ] Valid WordPress.org username in Contributors field
- [ ] GPLv2 or later license declared
- [ ] All required plugin header fields present
- [ ] readme.txt validates without errors
- [ ] Text domain matches plugin slug
- [ ] No trademark violations
- [ ] No restricted terms in name or description

### 15.2 WordPress Coding Standards

- [ ] Passes PHPCS with WPCS ruleset
- [ ] No PHP syntax errors
- [ ] Proper PHPDoc blocks on all methods
- [ ] Internationalization implemented
- [ ] Security best practices followed
- [ ] Database queries use prepare()
- [ ] Direct database queries properly documented

### 15.3 PHP 8.4 Compatibility

- [ ] `filesize()` false-return guarded
- [ ] `json_encode()` return value validated
- [ ] No deprecated functions used
- [ ] Type-safe comparisons (`===`, `!==`)
- [ ] No implicitly nullable parameters
- [ ] Compatible with PHP 8.1, 8.2, 8.3, 8.4

### 15.4 Multisite Compatibility

- [ ] Uses `$wpdb->base_prefix` consistently
- [ ] Uses site option functions
- [ ] Uninstaller targets correct table
- [ ] Tested on Multisite installation

### 15.5 Testing

- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Manual testing completed
- [ ] CI/CD pipeline green
- [ ] Code coverage meets minimum threshold (recommend 70%+)

---

## 16. Acceptance Criteria

The implementation SHALL be considered complete when:

1. All PHP 8.4 compatibility issues (#20, #21) are resolved and tested
2. WordPress.org compliance issues (#18, #19) are resolved
3. Multisite issue (#23) is verified fixed
4. All unit tests pass on PHP 8.1, 8.2, 8.3, and 8.4
5. All integration tests pass including Multisite scenarios
6. PHPCS reports zero errors with WPCS ruleset
7. Plugin validates successfully on WordPress.org plugin validator
8. Manual testing checklist is completed and signed off
9. Documentation (readme.txt, README.md, CHANGELOG.md) is updated
10. Version is set to 4.0.0 in all files

---

## 17. Timeline (Suggested)

| Phase | Duration | Activities |
|-------|----------|------------|
| **Phase 1: Fixes** | 1-2 days | Implement PHP 8.4 fixes, rename plugin, update headers |
| **Phase 2: Testing** | 2-3 days | Write and execute unit/integration tests, manual testing |
| **Phase 3: Documentation** | 1 day | Update all documentation, changelog |
| **Phase 4: Validation** | 1 day | Run WordPress.org validator, fix any issues |
| **Phase 5: Release** | 1 day | Tag v4.0.0, submit to WordPress.org |

---

## 18. Risks & Mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| WordPress.org rejects plugin name | High | Have alternative names ready: "Silent Witness", "Error Log Witness" |
| PHP 8.4 compatibility issues missed | Medium | Test on actual PHP 8.4 environment |
| Multisite bugs in production | Medium | Comprehensive integration tests on Multisite setup |
| Performance issues with large logs | Low | Test with 100MB+ debug.log files |
| Breaking changes confuse users | Low | Clear documentation, version bump to 4.0.0 |

---

## Appendix A: GitHub Issues Mapping

| Issue | Title | Status | Spec Section |
|-------|-------|--------|--------------|
| #18 | Plugin name contains restricted "WP" | Fix Required | 11.1, 15.1 |
| #19 | Contributors field invalid username | Fix Required | 11.2, 15.1 |
| #20 | filesize() false return | Fix Required | 11.3, 15.3 |
| #21 | json_encode() output guard | Fix Required | 11.4, 15.3 |
| #23 | Multisite table prefix mismatch | Verify Fixed | 11.5, 15.4 |

---

## Appendix B: Code Examples

### B.1 filesize() Guard Pattern

```php
public function ingest() {
    if (!file_exists($this->log_path)) {
        return sprintf(
            __('Log file not found at %s', 'silent-witness-for-wordpress'),
            $this->log_path
        );
    }

    $handle = fopen($this->log_path, 'r');
    if (!$handle) {
        return __('Could not open log file.', 'silent-witness-for-wordpress');
    }

    $last_offset = (int) get_site_option('silent_witness_log_offset', 0);
    $file_size = filesize($this->log_path);

    // Guard against false return (Issue #20)
    if (false === $file_size) {
        fclose($handle);
        return __('Could not determine log file size.', 'silent-witness-for-wordpress');
    }

    if ($file_size < $last_offset) {
        $last_offset = 0; // Log rotated
    }

    fseek($handle, $last_offset);
    
    // ... processing logic ...
}
```

### B.2 json_encode() Guard Pattern

```php
public function cli_command_export() {
    global $wpdb;
    
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM `{$this->table}` ORDER BY last_seen DESC LIMIT %d",
            1000
        )
    );
    // phpcs:enable

    $data = !empty($results) ? $results : [];
    
    // Guard against json_encode failure (Issue #21)
    $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (false === $json) {
        WP_CLI::error(
            __('Failed to encode logs as JSON: ', 'silent-witness-for-wordpress') 
            . json_last_error_msg()
        );
        return;
    }

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    WP_CLI::log($json);
}
```

### B.3 Multisite Table Prefix Pattern

```php
// Main plugin file
private function __construct() {
    global $wpdb;
    // Use base_prefix for Multisite compatibility (Issue #23)
    $this->table = $wpdb->base_prefix . 'silent_witness_logs';
    $this->log_path = WP_CONTENT_DIR . '/debug.log';
    // ...
}

// uninstall.php
global $wpdb;
// MUST match main plugin - use base_prefix
$table_name = $wpdb->base_prefix . 'silent_witness_logs';
$table = esc_sql($table_name);
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query("DROP TABLE IF EXISTS `{$table}`");
```

---

**End of Specification**
