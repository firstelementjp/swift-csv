# 🤝 Contributing

We welcome contributions to Swift CSV!

## How to Contribute

### Bug Reports

1. Open an [Issue](https://github.com/firstelementjp/swift-csv/issues)
2. Describe the bug in detail
3. Include reproduction steps
4. Add debug logs if available (set `WP_DEBUG` to true)

### Feature Requests

1. Open an [Issue](https://github.com/firstelementjp/swift-csv/issues)
2. Explain use cases
3. Share implementation ideas
4. Consider impact on existing functionality

### Code Contributions

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Create a Pull Request

## Development Environment

### Prerequisites

- Node.js 18.0.0 or higher
- PHP 7.4 or higher
- WordPress installation
- Composer
- direnv (recommended for environment setup)

### Setup

```bash
# Clone the repository
git clone https://github.com/firstelementjp/swift-csv.git
cd swift-csv

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Setup environment (optional but recommended)
cp .envrc.example .envrc
# Edit .envrc with your local settings
direnv allow
```

### Development Workflow

```bash
# Watch for changes during development
npm run dev

# Build assets for production
npm run build

# Lint JavaScript
npm run lint:js

# Format code
npm run format

# Check PHP coding standards
composer run phpcs
```

### Coding Standards

- Follow WordPress coding standards
- Write PHPDoc comments for all functions and classes
- Use `[Swift CSV]` prefix for debug logs
- Check code with PHPCS: `composer run phpcs`
- Format JavaScript with Prettier: `npm run format`

### Debug Logging

Add debug logging with consistent format:

```php
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( '[Swift CSV] Your message here' );
}
```

### Testing

- Test import/export with various CSV formats
- Test with different post types and custom fields
- Verify AJAX functionality in browser console
- Check responsive design on mobile devices

## Pull Requests

### Pre-submission Checklist

- [ ] Code follows WordPress coding standards
- [ ] JavaScript is linted and formatted
- [ ] Debug logging uses `[Swift CSV]` prefix
- [ ] Assets are built (`npm run build`)
- [ ] Documentation updated if needed
- [ ] Changelog updated for new features
- [ ] Tested in WordPress admin

### Review Process

1. Automated checks run (linting, standards)
2. Code review for functionality and standards
3. Testing verification
4. Merge decision

### Commit Messages

Use clear and descriptive commit messages:

```
feat: Add multi-value custom field support
fix: Resolve delimiter detection for complex CSV
docs: Update configuration documentation
refactor: Clean up duplicate CSS rules
```

## Project Structure

```
swift-csv/
├── assets/          # Frontend assets (CSS, JS)
├── includes/        # PHP classes
├── languages/        # Translation files
├── docs/            # Documentation
├── .github/         # GitHub workflows
├── package.json     # Node.js dependencies
├── composer.json    # PHP dependencies
└── swift-csv.php    # Main plugin file
```

## Getting Help

- Check [SKILL.md](../.github/skills/SKILL.md) for troubleshooting
- Review existing issues for similar problems
- Join discussions in GitHub Issues
- Refer to WordPress coding standards documentation
