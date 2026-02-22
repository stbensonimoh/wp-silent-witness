=== WP Silent Witness ===
Contributors: stbensonimoh
Donate link: https://github.com/sponsors/stbensonimoh
Tags: error-log, debug, logging, deduplication, monitoring
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 2.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zero-cost, high-performance log ingestion and deduplication for WordPress.

== Description ==

**WP Silent Witness** is a zero-cost, high-performance error log ingestion and deduplication plugin for WordPress.

It is designed for senior developers and consultants working in managed hosting environments (like WP Engine) where standard log files are often rotated, truncated, or difficult to access.

Standard WordPress 'debug.log' files are noisy and transient. Intermittent errors—the ones that happen once an hour or only during specific user actions—are easily lost.

Silent Witness solves this by:

1. **Ingesting from debug.log**: It reads and parses your existing WordPress debug log file (requires 'WP_DEBUG_LOG' to be enabled), capturing PHP errors, warnings, and notices.
2. **Deduplicating at the source**: It creates a unique hash for every error (Type + Message + File + Line). If an error happens 10,000 times, it occupies only **one row** in your database with an incrementing counter.
3. **Structured Export**: It provides a clean JSON export via WP-CLI, making it perfect for analysis by AI assistants or external tools.

= Requirements =

* **PHP**: 7.4 or higher
* **WordPress**: 6.0 or higher
* **Database**: MySQL 5.7+ or MariaDB 10.3+
* **WP-CLI**: 2.0+ (optional, for CLI commands)

= Privacy & Security =

* **Zero SaaS Cost**: No external subscriptions required.
* **Fast Hashing**: Uses MD5 for signature generation and 'ON DUPLICATE KEY UPDATE' for atomic, high-speed database writes.
* **Privacy**: Only stores essential error metadata (type, message, file path, line number, and deduplication counters). It does not log request context (URL, HTTP method, user ID), POST data, or cookies.

== Installation ==

= Method 1: Composer (Recommended for Developers) =

'composer require stbensonimoh/wp-silent-witness'

Or add to your 'composer.json':

    {
        "require": {
            "stbensonimoh/wp-silent-witness": "^2.0"
        }
    }

**Note:** Composer will install to 'wp-content/plugins/' by default. If using as an MU-plugin, move or symlink the package to 'wp-content/mu-plugins/'.

= Method 2: Manual ZIP Download =

1. Download the latest release from GitHub: https://github.com/stbensonimoh/wp-silent-witness/releases
2. Extract the ZIP file
3. For **must-use plugin**: upload 'wp-silent-witness.php' to 'wp-content/mu-plugins/'
4. For **standard plugin**: upload the entire 'wp-silent-witness' folder to 'wp-content/plugins/', then activate **WP Silent Witness** from **Plugins → Installed Plugins** in the WordPress admin.

= Prerequisites =

You must enable WordPress debug logging by adding these to your 'wp-config.php':

    define('WP_DEBUG', true);
    define('WP_DEBUG_LOG', true);

== Frequently Asked Questions ==

= Will this slow down my site? =

No. The ingestion runs via WordPress cron every 15 minutes, not on every page load. The database write uses an indexed hash lookup for efficient deduplication.

= How much database space does this use? =

Minimal. Each unique error occupies one row regardless of how many times it occurs. The table is indexed efficiently and stores only essential metadata.

= Can I use this on a multisite installation? =

Yes. The plugin uses 'get_site_option()' and 'update_site_option()' for network-wide consistency, and the logs table is shared across all sites.

= What do I need to enable for this to work? =

You must enable WordPress debug logging by adding these to your 'wp-config.php':

    define('WP_DEBUG', true);
    define('WP_DEBUG_LOG', true);

= How do I view the logs? =

Use WP-CLI: 'wp silent-witness export' will output a clean JSON report of all deduplicated errors.

= How do I clear the logs? =

Use WP-CLI: 'wp silent-witness clear' to wipe the records but keep the database structure. Use 'wp silent-witness destroy --yes' to completely remove everything.

== Screenshots ==

1. WP-CLI export showing deduplicated error logs in JSON format
2. Example output showing error counts and metadata

== Changelog ==

= 2.0.1 =
* Load plugin textdomain for front-end translation support
* Add comprehensive PHPDoc blocks to all methods and properties
* Document ON DUPLICATE KEY UPDATE ingestion strategy

= 2.0.0 =
* Initial release of WP Silent Witness.
* Zero-cost log ingestion and deduplication.
* WP-CLI support for export, clear, and destroy operations.
* Automatic database table creation.
* Hash-based deduplication with occurrence counting.

== Upgrade Notice ==

= 2.0.1 =
Minor fix for textdomain loading and improved documentation. No breaking changes.

== Credits ==

Developed by Benson Imoh (https://stbensonimoh.com) for high-performance WordPress environments.
