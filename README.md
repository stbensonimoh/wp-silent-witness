# WP Silent Witness

**WP Silent Witness** is a zero-cost, high-performance error trapping and de-duplication plugin for WordPress. 

It is designed for senior developers and consultants working in managed hosting environments (like WP Engine) where standard log files are often rotated, truncated, or difficult to access.

## Why it exists

Standard WordPress `debug.log` files are noisy and transient. Intermittent errors—the ones that happen once an hour or only during specific user actions—are easily lost. 

Silent Witness solves this by:
1. **Intercepting every error**: It catches warnings, notices, exceptions, and even fatal "White Screen of Death" errors.
2. **De-duplicating at the source**: It creates a unique hash for every error (Type + Message + File + Line). If an error happens 10,000 times, it occupies only **one row** in your database with an incrementing counter.
3. **Structured Export**: It provides a clean JSON export via WP-CLI, making it perfect for analysis by AI assistants or external tools.

## Lifecycle Management

As an MU-plugin, Silent Witness handles its own lifecycle without manual activation:

- **Auto-Installation**: On first run, it automatically creates the `wp_silent_witness_logs` database table. It uses a "self-healing" check that ensures the table exists without impacting performance.
- **Self-Cleaning**: Includes an `uninstall.php` file for clean database removal if transitioned to a standard plugin.
- **Manual Teardown**: Use WP-CLI for immediate, destructive cleanup (see below).

## Usage

### Exporting Logs
To get a clean JSON report of all de-duplicated errors, run:
```bash
wp silent-witness export
```

### Clearing Logs (Reset Counter)
To wipe the records but keep the database structure:
```bash
wp silent-witness clear
```

### Destruction (Tear Down)
To completely remove the database table and all associated transients:
```bash
wp silent-witness destroy --yes
```

## Installation

1. Create a directory named `wp-content/mu-plugins` if it doesn't exist.
2. Upload `wp-silent-witness.php` to that directory.
3. (Optional) Upload `uninstall.php` to the same directory if you plan to move it to a standard plugin later.

## Security & Performance
- **Zero SaaS Cost**: No external subscriptions required.
- **Fast Hashing**: Uses MD5 for signature generation and `ON DUPLICATE KEY UPDATE` for atomic, high-speed database writes.
- **Privacy**: Only captures basic request context (URL, Method, User ID). No sensitive POST data or cookies are logged by default.

## License
MIT
