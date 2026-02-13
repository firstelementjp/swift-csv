# Contributing to Swift CSV

Thanks for taking the time to contribute!

This project is a WordPress plugin that provides CSV import/export with support for custom post types, taxonomies, custom fields, and ACF fields.

## Where to Start

- **Bug reports**: open an issue with steps to reproduce and expected/actual behavior.
- **Feature requests**: open an issue describing the use case and desired UX.
- **Security issues**: please do **not** open a public issue. Contact the maintainers privately.

## Development Setup

### Prerequisites

- PHP **7.4+**
- Node.js **18+**
- Composer

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
- After editing `assets/js/admin-scripts.js` or `assets/css/admin-style.css`, rebuild minified files:

```bash
npm run build
```

Optional (if you change JS formatting/style):

```bash
npm run lint:js
npm run format
```

## Project Conventions

### CSV column prefixes

- `tax_` for taxonomy terms
- `acf_` for ACF fields
- `cf_` for other custom fields

### ACF gotcha (important)

When using ACF functions, **always pass `$post_id`**.

```php
// WRONG
get_field_object( $field_name );

// CORRECT
get_field_object( $field_name, $post_id, false, false );
```

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

## Reporting Bugs

Please include:

- WordPress version
- PHP version
- Steps to reproduce
- Expected behavior vs actual behavior
- Relevant logs (e.g., `debug.log`)

## License

By contributing, you agree that your contributions will be licensed under the project license (GPL-2.0+).
