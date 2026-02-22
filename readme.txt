=== WP Silent Witness ===
Contributors: stbensonimoh
Donate link: https://github.com/sponsors/stbensonimoh
Tags: error-log, debug, logging, deduplication, monitoring
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 2.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zero-cost, high-performance log ingestion and deduplication for WordPress.

== Description ==

**WP Silent Witness** is a zero-cost, high-performance error log ingestion and deduplication plugin for WordPress.

It is designed for senior developers and consultants working in managed hosting environments (like WP Engine) where standard log files are often rotated, truncated, or difficult to access.

Standard WordPress `debug.log` files are noisy and transient. Intermittent errors — the ones that happen once an hour or only during specific user actions — are easily lost.

WP Silent Witness solves this by ingesting your `debug.log` into a persistent, deduplicated database table. Every error signature (type + message + file + line) is stored exactly once; subsequent occurrences simply increment a counter and update a `last_seen` timestamp.

= Key Features =

* **Automatic ingestion** via a configurable WordPress Cron schedule.
* **High-performance deduplication** using `INSERT ... ON DUPLICATE KEY UPDATE` with an MD5 hash primary key — no SELECT-before-INSERT race conditions.
* **WP-CLI support** — trigger ingestion, export logs as JSON, clear the table, or fully destroy the plugin's data from the command line.
* **Admin UI** — an "Ingest Now" button in the WordPress dashboard backed by an AJAX handler.
* **Zero external dependencies** — uses only WordPress core APIs and PHP built-ins.

== Installation ==

1. Upload the `wp-silent-witness` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Ensure `WP_DEBUG` and `WP_DEBUG_LOG` are enabled in your `wp-config.php`.
4. The plugin will automatically ingest logs on the configured cron schedule.

== Frequently Asked Questions ==

= Does this plugin work on managed hosts? =

Yes. It reads from the standard `wp-content/debug.log` path and persists data to the WordPress database, requiring no filesystem write access beyond what WordPress already has.

= Can I run ingestion manually? =

Yes. Use the WP-CLI command `wp silent-witness ingest` or click **Ingest Now** in the WP Admin page.

== Changelog ==

= 2.0.1 =
* fix: load plugin textdomain for front-end translation support.

= 2.0.0 =
* feat: initial release with cron-based log ingestion, deduplication, and WP-CLI support.

== Upgrade Notice ==

= 2.0.1 =
Minor fix for textdomain loading. No database changes.
