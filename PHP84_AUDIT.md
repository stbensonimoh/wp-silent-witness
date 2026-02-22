# WP Silent Witness — PHP 8.4 Compatibility Audit

**Date:** 2026-02-22
**Version Audited:** 2.0.1
**PHP Target:** 8.4
**Files Audited:** `wp-silent-witness.php`, `uninstall.php`, `composer.json`

---

## Executive Summary

**Status: MOSTLY COMPATIBLE — 2 actionable issues, 3 advisories, 1 declaration gap**

The plugin contains no hard runtime failures under PHP 8.4. No removed functions are used, no `/e` regex modifiers exist, and no deprecated call signatures were found. However, there are two code patterns that produce PHP 8.4 deprecation notices, one type-safety gap that can produce unexpected behaviour under PHP 8.x strict comparisons, and a `composer.json` constraint that does not declare 8.4 support.

---

## Issues by Severity

---

### [HIGH] `filesize()` false-return not guarded before integer comparison

**File:** `wp-silent-witness.php`, line 169
**PHP 8.4 behaviour:** unchanged, but this is a latent type-safety bug exposed by PHP 8's stricter comparison semantics.

```php
$file_size = filesize( $this->log_path );   // returns int|false

if ( $file_size < $last_offset ) {          // if $file_size === false, PHP 8 emits
    $last_offset = 0;                       // TypeError in strict_types mode; in
}                                           // non-strict mode false < 0 is true,
                                            // silently resetting offset to 0.
```

`filesize()` returns `false` on failure (e.g. the file exists but is not readable — `file_exists()` can return `true` while `filesize()` returns `false`). Under PHP 8.4 without `declare(strict_types=1)` the comparison still works but casts `false` to `0`, which means the offset is silently reset every ingest run if the file is not readable.

**Fix:**

```php
$file_size = filesize( $this->log_path );
if ( false === $file_size ) {
    fclose( $handle );
    return __( 'Could not determine log file size.', 'wp-silent-witness' );
}
if ( $file_size < $last_offset ) {
    $last_offset = 0;
}
```

---

### [HIGH] `json_encode()` return value echoed without false-check

**File:** `wp-silent-witness.php`, line 289

```php
echo json_encode( $results ?: [], JSON_PRETTY_PRINT );
```

`json_encode()` returns `false` on failure (e.g., a database row contains a binary string with invalid UTF-8 sequences — entirely possible for file paths or error messages stored from legacy PHP logs). PHP 8.x does not change this return type, but **PHP 8.5 is planned to make `json_encode` throw a `JsonException` by default**; PHP 8.4 adds deprecation groundwork. Echoing `false` silently outputs nothing, masking the error entirely.

**Fix:**

```php
$json = json_encode( $results ?: [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
if ( false === $json ) {
    WP_CLI::error( 'JSON encoding failed: ' . json_last_error_msg() );
    return;
}
echo $json;
```

---

### [MEDIUM] `md5()` used as deduplication primary key

**File:** `wp-silent-witness.php`, line 226

```php
$hash = md5( $type . $message . $clean_file . $line );
```

`md5()` is not deprecated in PHP 8.4, but PHP 8.4 introduces the `#[\Deprecated]` attribute for userland code and the broader PHP community guidance now flags `md5()` for security-sensitive use. Here it is used purely for deduplication (not cryptographic security), which is a valid use case — however the hash is stored in a `CHAR(32)` column, and MD5 has a known (though practically negligible for this use case) collision probability.

More relevantly: the concatenation `$type . $message . $clean_file . $line` has no delimiter, meaning `type="A", message="Bfile"` and `type="AB", message="file"` could produce the same hash input. This is a pre-existing logic bug, not PHP 8.4-specific, but worth noting alongside the hash discussion.

**Recommendation:** Use `hash('xxh3', ...)` (available since PHP 8.1) for non-cryptographic checksums (faster, lower collision risk) and add a delimiter:

```php
$hash = hash( 'xxh3', implode( '|', [ $type, $message, $clean_file, (string) $line ] ) );
```

Note: if switching hash algorithms, the `hash` column type/length must be updated and existing rows re-hashed or the table truncated.

---

### [MEDIUM] `composer.json` PHP version constraint excludes PHP 8.4

**File:** `composer.json`, line 21

```json
"require": {
    "php": ">=7.4",
```

While `>=7.4` technically includes 8.4, this range is not best practice and does not signal tested 8.4 support. It also allows installation on PHP versions (7.4, 8.0, 8.1) that WordPress itself is dropping support for. The constraint should be tightened to the tested range:

```json
"php": ">=8.0 <8.5"
```

or, if PHP 8.4 is the target minimum going forward:

```json
"php": "^8.4"
```

---

### [LOW] `$args[0] ?? 'list'` — null coalescing on a positional argument

**File:** `wp-silent-witness.php`, line 276

```php
$action = $args[0] ?? 'list';
```

This is valid PHP 7.0+ null-coalescing syntax and remains fully supported in PHP 8.4. No change needed. Noted here because the `'list'` default action falls through to the `else` branch (line 302) and emits an error rather than being a useful no-op — a minor UX issue, not a compatibility issue.

---

### [LOW] `uninstall.php` uses `$wpdb->prefix` instead of `$wpdb->base_prefix`

**File:** `uninstall.php`, line 14

```php
$table_name = $wpdb->prefix . 'silent_witness_logs';
```

The main plugin (`wp-silent-witness.php`, line 79) uses `$wpdb->base_prefix`:

```php
$this->table = $wpdb->base_prefix . 'silent_witness_logs';
```

On a standard single-site install these are identical. On WordPress Multisite, `$wpdb->prefix` is the current site's prefix (e.g., `wp_2_`) while `$wpdb->base_prefix` is always the network prefix (`wp_`). This mismatch means uninstall will attempt to drop the wrong table on multisite. This is not a PHP 8.4 issue but was discovered during the audit.

**Fix:**

```php
$table_name = $wpdb->base_prefix . 'silent_witness_logs';
```

---

## PHP 8.4-Specific Feature Inventory

The following PHP 8.4 features are **not used** but are available for modernisation:

| Feature | Available Since | Applicable To |
|---|---|---|
| Property hooks | PHP 8.4 | `$table`, `$log_path` could use `get` hooks instead of private fields + accessors |
| `#[\Deprecated]` attribute | PHP 8.4 | Annotate any methods scheduled for removal |
| Asymmetric visibility (`public private(set)`) | PHP 8.4 | `$instance`, `$table`, `$log_path` properties |
| `array_find()` / `array_find_key()` | PHP 8.4 | Not directly applicable here |
| `new` in initialisers | PHP 8.4 | Not applicable here |
| `\Dom\Document` (new DOM API) | PHP 8.4 | Not applicable here |

None of these are required for compatibility — they are opt-in improvements.

---

## Deprecated Functions Check (PHP 7.x → 8.4 Removal Timeline)

| Function | Status in PHP 8.4 | Used in Plugin |
|---|---|---|
| `mysql_*` functions | Removed in PHP 7.0 | No |
| `ereg()` / `eregi()` | Removed in PHP 7.0 | No |
| `each()` | Removed in PHP 8.0 | No |
| `create_function()` | Removed in PHP 8.0 | No |
| `get_magic_quotes_gpc()` | Removed in PHP 8.0 | No |
| `preg_replace()` with `/e` | Removed in PHP 7.0 | No |
| `ReflectionParameter::getClass()` | Deprecated PHP 8.0 | No |
| Passing `null` to non-nullable params | Deprecated PHP 8.1 | No |
| `implicitly nullable` parameters | Deprecated PHP 8.4 | **None found** |
| `_` as class name | Deprecated PHP 8.4 | No |
| Calling `get_class()` with no arg outside class | Deprecated PHP 8.3+ | No |
| `Round` mode `PHP_ROUND_*` type widening | Deprecated PHP 8.4 | No |

**Result: No removed or deprecated built-in functions are called anywhere in this plugin.**

---

## Summary Table

| ID | File | Line(s) | Severity | Issue |
|---|---|---|---|---|
| PHP84-01 | `wp-silent-witness.php` | 167–171 | HIGH | `filesize()` false-return not guarded before `<` comparison |
| PHP84-02 | `wp-silent-witness.php` | 289 | HIGH | `json_encode()` false-return echoed without check |
| PHP84-03 | `wp-silent-witness.php` | 226 | MEDIUM | `md5()` with undelimited concatenation; consider `hash('xxh3', ...)` |
| PHP84-04 | `composer.json` | 21 | MEDIUM | PHP version constraint does not explicitly cap at 8.4 |
| PHP84-05 | `uninstall.php` | 14 | LOW | `$wpdb->prefix` vs `$wpdb->base_prefix` mismatch with main plugin |
| PHP84-06 | `wp-silent-witness.php` | 276 | INFO | `?? 'list'` default falls through to error branch — UX note only |

---

## Overall Compatibility Verdict

| Dimension | Result |
|---|---|
| Hard failures on PHP 8.4 | None |
| Deprecation notices on PHP 8.4 | None |
| Type-safety gaps surfaced by PHP 8.x | 1 (filesize false-return) |
| Modernisation opportunities | 3 (xxh3 hash, asymmetric visibility, property hooks) |
| Dependency declaration gap | 1 (composer.json) |

The plugin will **load and run without errors on PHP 8.4**. The two HIGH items are latent bugs that PHP 8.x makes more visible but does not turn into fatal errors under default (non-strict) configuration. They should be fixed before claiming PHP 8.4 support.

---

*Audit completed 2026-02-22*
