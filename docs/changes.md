# 📋 Changelog

## [0.9.6] - 2026-02-13

### Improvements

- **Import refactoring** - Improved maintainability for future Pro integration

---

## [0.9.5] - 2026-02-08

### Improvements

- **Code cleanup and optimization** - Removed duplicate code and unnecessary comments across all PHP files
- **Debug logging system** - Added comprehensive debug logging with `[Swift CSV]` prefix for better troubleshooting
- **JavaScript debugging** - Implemented conditional debug logging in admin scripts based on WP_DEBUG
- **Documentation improvements** - Updated SKILL.md with troubleshooting knowledge base
- **Build system enhancements** - Improved GitHub Actions workflows for better automation

### Bug Fixes

- **CSS optimization issues** - Fixed design breaks caused by aggressive CSS cleanup
- **File naming consistency** - Updated references to use correct JavaScript file names
- **Translation file cleanup** - Removed outdated translation JSON files

### Technical Debt

- **Removed duplicate functions** - Cleaned up redundant PHP functions and CSS rules
- **Code standardization** - Applied consistent coding standards across all files
- **Documentation updates** - Ensured all documentation reflects current implementation

---

## [0.9.4] - 2026-02-07

### New Features

- **Environment setup improvements** - Enhanced direnv configuration for better development experience
- **Debug logging enhancements** - Improved debug output with consistent formatting
- **Build process optimization** - Streamlined npm build scripts

### Improvements

- **Development environment** - Better WP-CLI compatibility and database connection handling
- **Error handling** - Enhanced error messages and debugging information
- **Code quality** - Improved code organization and maintainability

### Bug Fixes

- **WP-CLI connection issues** - Fixed database variable quoting problems
- **Environment variable handling** - Resolved path issues with spaces in directory names

---

## [0.9.3] - 2026-02-06

### New Features

- **AJAX import/export system** - Complete rewrite with modern AJAX handling
- **Real-time progress tracking** - Dynamic progress bars for large file processing
- **Enhanced error handling** - Better error reporting and user feedback
- **WordPress coding standards** - Full compliance with WPCS

### Improvements

- **Performance optimization** - Chunked processing for large CSV files
- **User interface** - Modern responsive design with better UX
- **Memory management** - Optimized memory usage for large datasets
- **Internationalization** - Complete Japanese translation support

### Bug Fixes

- **ACF field export** - Fixed empty ACF columns in exported CSV
- **JavaScript progress elements** - Resolved DOM selector issues after UI refactor
- **Date formatting** - Fixed filename date format bug
- **AJAX response format** - Added proper success flag for WordPress compatibility

---

## [0.9.2] - 2026-01-31

### New Features

- **Multi-value custom fields support** - Import/export multiple values using pipe separator (e.g., "value1|value2|value3")
- **WordPress coding standards compliance** - Applied WPCS to exporter class

### Improvements

- Backward compatible with single-value custom fields
- Automatic empty value filtering in multi-value processing
- Updated documentation with usage examples
- Enhanced code quality and maintainability

## [0.9.1] - 2026-01-31

### New Features

- Docsify-based documentation system
- Automatic API documentation generation
- GitHub Pages deployment support

### Improvements

- CSV processing performance improvements
- Memory usage optimization
- Enhanced error handling

### Bug Fixes

- Memory leak fix for large file processing
- Japanese character encoding issue fix

## [0.9.0] - 2026-01-15

### New Features

- Batch processing functionality
- Custom field support
- Export functionality

---

For complete changelog, see [GitHub repository](https://github.com/firstelementjp/swift-csv/commits/main).
