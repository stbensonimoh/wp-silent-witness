# WP Silent Witness

**WP Silent Witness** is a zero-cost, high-performance error trapping and de-duplication plugin for WordPress.

It is designed for senior developers and consultants working in managed hosting environments (like WP Engine) where standard log files are often rotated, truncated, or difficult to access.

## Requirements

- **PHP**: 8.0 or higher
- **WordPress**: 6.0 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **WP-CLI**: 2.0+ (optional, for CLI commands)

## Why it exists

Standard WordPress `debug.log` files are noisy and transient. Intermittent errors—the ones that happen once an hour or only during specific user actions—are easily lost.

Silent Witness solves this by:
1. **Intercepting every error**: It catches warnings, notices, exceptions, and even fatal "White Screen of Death" errors.
2. **De-duplicating at the source**: It creates a unique hash for every error (Type + Message + File + Line). If an error happens 10,000 times, it occupies only **one row** in your database with an incrementing counter.
3. **Structured Export**: It provides a clean JSON export via WP-CLI, making it perfect for analysis by AI assistants or external tools.

## Installation

### Method 1: Composer (Recommended)

```bash
composer require stbensonimoh/wp-silent-witness
```

Or add to your `composer.json`:

```json
{
    "require": {
        "stbensonimoh/wp-silent-witness": "^2.0"
    }
}
```

### Method 2: Manual ZIP Download

1. Download the latest release from [GitHub Releases](https://github.com/stbensonimoh/wp-silent-witness/releases)
2. Extract the ZIP file
3. Upload `wp-silent-witness.php` to `wp-content/mu-plugins/` (for must-use plugin)
4. Or upload to `wp-content/plugins/` (for standard plugin activation)

### Method 3: Git Clone

```bash
cd wp-content/mu-plugins
git clone https://github.com/stbensonimoh/wp-silent-witness.git
```

## Lifecycle Management

As an MU-plugin, Silent Witness handles its own lifecycle without manual activation:

- **Auto-Installation**: On first run, it automatically creates the `wp_silent_witness_logs` database table. It uses a "self-healing" check that ensures the table exists without impacting performance.
- **Self-Cleaning**: Includes an `uninstall.php` file for clean database removal if transitioned to a standard plugin.
- **Manual Teardown**: Use WP-CLI for immediate, destructive cleanup (see below).

## Usage

### WP-CLI Commands

#### Ingest Logs
Manually trigger log ingestion from `debug.log`:
```bash
wp silent-witness ingest
```

#### Export Logs
To get a clean JSON report of all de-duplicated errors:
```bash
wp silent-witness export
```

#### Clearing Logs (Reset Counter)
To wipe the records but keep the database structure:
```bash
wp silent-witness clear
```

#### Destruction (Tear Down)
To completely remove the database table and all associated options:
```bash
wp silent-witness destroy --yes
```

## Contributing

We welcome contributions! Please follow these guidelines:

### Reporting Issues

- Use [GitHub Issues](https://github.com/stbensonimoh/wp-silent-witness/issues)
- Include WordPress version, PHP version, and steps to reproduce
- For bugs, include relevant error messages or log excerpts

### Pull Requests

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature-name`
3. Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
4. Run `phpcs --standard=WordPress-Docs` before submitting
5. Commit with descriptive messages following [Conventional Commits](https://www.conventionalcommits.org/)
6. Push to your fork and submit a PR

### Development Setup

```bash
# Install dependencies
composer install

# Run coding standards checks
composer run phpcs

# Run tests (if available)
composer run test
```

## Security & Performance

- **Zero SaaS Cost**: No external subscriptions required.
- **Fast Hashing**: Uses MD5 for signature generation and `ON DUPLICATE KEY UPDATE` for atomic, high-speed database writes.
- **Privacy**: Only captures basic request context (URL, Method, User ID). No sensitive POST data or cookies are logged by default.

## Frequently Asked Questions

**Q: Will this slow down my site?**

A: No. The ingestion runs via WordPress cron every 15 minutes, not on every page load. The database write uses an indexed hash lookup (O(1)) for deduplication.

**Q: How much database space does this use?**

A: Minimal. Each unique error occupies one row regardless of how many times it occurs. The table is indexed efficiently and stores only essential metadata.

**Q: Can I use this on a multisite installation?**

A: Yes. The plugin uses `get_site_option()` and `update_site_option()` for network-wide consistency, and the logs table is shared across all sites.

## Changelog

### 2.0.1
- Fixed typo in inline comment ("Fudraise" → "Fundraise")
- Added comprehensive PHPDoc blocks to all methods and properties
- Added `@since` metadata to class-level docblock
- Documented ON DUPLICATE KEY UPDATE ingestion strategy

### 2.0.0
- Initial release of WP Silent Witness
- Zero-cost log ingestion and deduplication
- WP-CLI support for export, clear, and destroy operations
- Automatic database table creation
- Hash-based deduplication with occurrence counting

## License

MIT

## Credits

Developed by [Benson Imoh](https://stbensonimoh.com) for high-performance WordPress environments.
