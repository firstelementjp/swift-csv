# Contributing to Swift CSV

Thanks for taking the time to contribute!

This project is a WordPress plugin that provides CSV import/export with support for custom post types, taxonomies, and custom fields.

## Where to Start

- **Bug reports**: open an issue with steps to reproduce and expected/actual behavior.
- **Feature requests**: open an issue describing the use case and desired UX.
- **Security issues**: please do **not** open a public issue. Contact the maintainers privately.

## Development Setup

### Prerequisites

- PHP **8.1+**
- Node.js **18+**
- Composer
- OpenSSL extension

### Install dependencies

```bash
composer install
npm ci
```

### Build assets

The admin UI uses minified assets built by esbuild.

```bash
npm run build
```

### Watch mode

```bash
npm run dev
```

## Coding Standards

### PHP

- Follow WordPress Coding Standards (WPCS).
- Add **PHPDoc** for classes, functions, and methods.
- Write code comments in **English**.

Run linting:

```bash
composer phpcs
```

Auto-fix when possible:

```bash
composer phpcbf
```

### JavaScript / CSS

- Write code comments in **English**.
- After editing JavaScript or CSS files in `assets/js/` or `assets/css/`, rebuild minified files:

```bash
npm run build
```

The build process handles:

- `assets/js/swift-csv-*.js` → `assets/js/swift-csv-*.min.js`
- `assets/css/swift-csv-style.css` → `assets/css/swift-csv-style.min.css`
- `assets/js/export/swift-csv/*.js` → `assets/js/export/swift-csv/*.min.js`

```bash
npm run lint:js
npm run format
```

## Regression Test Checklist (Import)

When you refactor import code or touch CSV parsing / row processing / persistence, run the checklist below in a real WordPress environment.

### 1) Dry Run

1. Open **Admin Dashboard → Tools → Swift CSV → Import**
2. Select a known-good CSV
3. Run **Dry Run** for:
    - New posts
    - Update existing posts
4. Confirm:
    - Import completes without errors
    - Progress UI updates and reaches 100%
    - Browser console has no JSON parse errors
    - Log summary shows created/updated/errors with expected labels

### 2) Real Import (Non-Dry Run)

1. Run a small import that creates at least 1 new post
2. Run a small import that updates at least 1 existing post
3. Confirm:
    - Posts are actually created/updated in WordPress
    - Progress UI updates and reaches 100%
    - Browser console has no JSON parse errors
    - `debug.log` does not contain PHP fatal errors
    - Taxonomies and custom fields are applied as expected

### 3) Asset rebuild (when JS/CSS changes)

If you changed files under `assets/js/` or `assets/css/`, rebuild and include the updated minified outputs:

```bash
npm run build
```

## Project Conventions

### CSV column prefixes

- `tax_` for taxonomy terms
- `cf_` for other custom fields

### JavaScript internationalization

- **Never** use `__()` or other i18n functions directly in JavaScript
- All translations should be handled in PHP using i18n functions
- Pass translated strings to JavaScript via `wp_localize_script`
- JavaScript should only reference the localized array strings

For recurring pitfalls and fix patterns, see:

- `.github/skills/SKILL.md`

## Branching / Pull Requests

### Branch strategy

- `main`: stable releases
- `develop`: ongoing development
- `feature/*`: feature branches
- `fix/*`: bugfix branches

### Recommended workflow

1. Fork the repo
2. Create a branch from `develop`
3. Make changes in small, reviewable commits
4. Open a Pull Request targeting `develop`

### Pull Request checklist

- [ ] The change is described clearly (what/why/how)
- [ ] You tested the feature/bugfix in a real WordPress environment
- [ ] `composer phpcs` passes (or you explain why it cannot)
- [ ] JS/CSS changes include updated minified files (`npm run build`)
- [ ] Docs updated if behavior changed
- [ ] PHP version requirement (8.0+) is still appropriate
- [ ] No debug code or `error_log()` statements left in committed code

## Reporting Bugs

Please include:

- WordPress version
- PHP version
- Steps to reproduce
- Expected behavior vs actual behavior
- Relevant logs (e.g., `debug.log`)

## License

By contributing, you agree that your contributions will be licensed under the project license (GPL-2.0+).
