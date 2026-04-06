# Environment Setup Conventions

## Overview

This document outlines the recommended local development setup for Swift CSV, focusing on Local-style WordPress installs, Node/Composer tooling, and WP-CLI friendly environment conventions.

## Local Development Baseline

Use a local WordPress site with the plugin checked out under `wp-content/plugins/swift-csv`.

Recommended baseline:

```bash
php >= 8.1
wordpress >= 6.6
node >= 22
composer installed
npm installed
wp-cli available locally if possible
```

## direnv + wp-config.php Pattern

If you use `direnv`, keep local-only values in `.envrc` and convert them to WordPress constants in `wp-config.php`.

Typical examples for local development:

```bash
# .envrc (never commit this file)
export DB_HOST=localhost
export DB_NAME=local
export DB_USER=root
export DB_PASSWORD=root
export WP_PATH="/Users/name/Local Sites/example/app/public"
export WP_TESTS_DIR="/absolute/path/to/vendor/wp-phpunit/wp-phpunit"
```

```php
// wp-config.php
define( 'DB_HOST', getenv( 'DB_HOST' ) ?: 'localhost' );
define( 'DB_NAME', getenv( 'DB_NAME' ) ?: 'local' );
define( 'DB_USER', getenv( 'DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'DB_PASSWORD' ) ?: 'root' );
```

### Why This Pattern?

1. **Security**: Local-only secrets and paths stay out of Git
2. **Flexibility**: Easy to change values without touching code
3. **WordPress Standard**: Uses familiar constant pattern in `wp-config.php`
4. **WP-CLI Friendly**: Shared variables make local shell commands repeatable
5. **Team Sharing**: Environment expectations can be documented without committing real values

## Security Guidelines

### ✅ Do

- Add `.envrc` to `.gitignore`
- Keep machine-specific paths in local-only files
- Use environment-specific values for DB and WP paths
- Test WP-CLI commands after changing local configuration

### ❌ Don't

- Commit local DB credentials or private keys
- Hard-code local machine paths in plugin source
- Assume paths without testing when directories contain spaces
- Share sensitive values in team chats

## File Structure

```
project/
├── .envrc                 # Local direnv values (ignored)
├── .gitignore             # Ignores .envrc and build artifacts
├── wp-config.php          # Reads local constants/DB settings
└── wp-content/plugins/
    └── swift-csv/         # Plugin repository
```

## Development Workflow

### 1. Initial Setup

```bash
# Create local environment file if using direnv
touch .envrc

# Edit local values
nano .envrc

# Allow direnv
direnv allow
```

### 2. Testing Environment Variables

```bash
# Verify variables are set
env | grep -E "(DB_|WP_|PHPUNIT_)"

# Test WordPress constants
wp --path="$WP_PATH" eval "
echo 'ABSPATH: ' . ABSPATH . PHP_EOL;
echo 'Site URL: ' . get_site_url() . PHP_EOL;
"
```

### 3. Install Dependencies

```bash
composer install
npm install
```

### 4. Day-to-Day Development

```bash
# PHP quality checks
composer phpcs
composer phpcbf

# JS/CSS build and lint
npm run lint:js
npm run build
npm run dev

# PHPUnit
composer test

# Local release validation
./test-release.sh
```

## PHPUnit Notes

Swift CSV uses `tests/bootstrap.php` and `phpunit.xml` to locate WordPress and the WP test library.

```bash
# If WP test library path is custom
export WP_TESTS_DIR="/absolute/path/to/vendor/wp-phpunit/wp-phpunit"

# Then run tests
composer test
```

## Best Practices

### Local Path Handling

- Quote filesystem paths that contain spaces
- Do not quote DB host/name/user values unless the environment requires it
- Re-test WP-CLI commands after path changes

### Tooling Verification

- `composer phpcs` should target `includes/`
- `npm run lint:js` should complete without errors
- `npm run build` should regenerate minified assets successfully
- `composer test` should run from the plugin root

### Build Artifacts

- Generated `.min.js`, `.min.css`, `test-release/`, `tests/results/`, and `.phpunit.result.cache` are local/build artifacts unless intentionally committed
- Validate release packaging with `./test-release.sh`, then verify the working tree is clean afterward

## Troubleshooting

### Common Issues

1. **Variables not available**: Check direnv is loaded (`direnv status`)
2. **WP-CLI cannot connect**: Re-check DB vars and quoted paths
3. **PHPUnit bootstrap fails**: Verify `WP_TESTS_DIR` or local WordPress discovery paths
4. **Build output missing**: Re-run `npm install` and `npm run build` from the plugin root

### Debug Commands

```bash
# Check direnv status
direnv status

# Verify environment variables
env | grep -E "(DB_|WP_|PHPUNIT_)"

# Test WP-CLI
wp --path="$WP_PATH" --info

# Test plugin root commands
composer phpcs
npm run lint:js
```

## Team Collaboration

### Onboarding New Developers

```bash
# Standard onboarding flow
git clone repository
cd swift-csv
composer install
npm install
composer test  # Verify setup
npm run build  # Verify frontend tooling
```

## References

- [WordPress Environment Constants](https://wordpress.org/support/article/editing-wp-config-php/#wordpress-debug-mode)
- [direnv Documentation](https://direnv.net/)
- [Security Best Practices](../troubleshooting/security-pitfalls.md)
